<?php

/*
 * This file is part of the gromnan/devnull-notifier package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\Notifier\Bridge\DevNull;

use phpseclib4\Common\Functions\Strings;
use phpseclib4\Crypt\Common\PrivateKey;
use phpseclib4\Crypt\EC;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Crypt\RSA;
use Symfony\Component\Notifier\Exception\LogicException;

/**
 * Signs a payload with an SSH private key, using the SSHSIG format.
 *
 * This is the same signature as the one produced by `ssh-keygen -Y sign`, which is what
 * dev-null.sh verifies. The private key is read from a file and never leaves the machine.
 *
 * @see https://github.com/openssh/openssh-portable/blob/master/PROTOCOL.sshsig
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class SshSigner
{
    public const NAMESPACE = 'devnull-commit';

    private const MAGIC = 'SSHSIG';
    private const VERSION = 1;

    private ?PrivateKey $key = null;

    public function __construct(
        #[\SensitiveParameter] private string $privateKey,
        #[\SensitiveParameter] private ?string $passphrase = null,
        private string $namespace = self::NAMESPACE,
    ) {
    }

    /**
     * Returns the base64-encoded SSHSIG signature of the payload.
     */
    public function sign(string $payload): string
    {
        $key = $this->loadKey();

        if ($key instanceof EC\PrivateKey && 'Ed25519' === $key->getCurve()) {
            $algorithm = 'ssh-ed25519';
            $hash = 'sha512';
            $signature = $key->sign($this->signedData($payload, $hash));
        } elseif ($key instanceof RSA\PrivateKey) {
            $algorithm = 'rsa-sha2-512';
            $hash = 'sha512';
            $signature = $key->withHash($hash)->withPadding(RSA::SIGNATURE_PKCS1)->sign($this->signedData($payload, $hash));
        } else {
            throw new LogicException(\sprintf('Unsupported SSH key type. Use an Ed25519 or RSA key, got "%s".', $key::class));
        }

        $publicKey = $key->getPublicKey()->toString('OpenSSH', ['binary' => true]);

        $blob = self::MAGIC
            .pack('N', self::VERSION)
            .Strings::packSSH2('s', $publicKey)
            .Strings::packSSH2('s', $this->namespace)
            .Strings::packSSH2('s', '')
            .Strings::packSSH2('s', $hash)
            .Strings::packSSH2('s', Strings::packSSH2('ss', $algorithm, $signature));

        return base64_encode($blob);
    }

    /**
     * The data covered by the signature: the magic, the namespace and the hash of the payload.
     */
    private function signedData(string $payload, string $hash): string
    {
        return self::MAGIC.Strings::packSSH2('ssss', $this->namespace, '', $hash, hash($hash, $payload, true));
    }

    private function loadKey(): PrivateKey
    {
        if (null !== $this->key) {
            return $this->key;
        }

        $material = @file_get_contents($this->privateKey);

        if (false === $material) {
            throw new LogicException(\sprintf('Unable to read the SSH private key from "%s".', $this->privateKey));
        }

        try {
            return $this->key = PublicKeyLoader::loadPrivateKey($material, $this->passphrase);
        } catch (\Exception $e) {
            throw new LogicException(\sprintf('Unable to load the SSH private key from "%s": %s', $this->privateKey, $e->getMessage()), 0, $e);
        }
    }
}
