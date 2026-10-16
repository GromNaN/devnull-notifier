<?php

/*
 * This file is part of the gromnan/devnull-notifier package.
 *
 * (c) Jérôme Tamarelle <jerome.tamarelle@mongodb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\Notifier\Bridge\DevNull\Tests;

use phpseclib4\Common\Functions\Strings;
use phpseclib4\Crypt\EC;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Crypt\RSA;

/**
 * Verifies a SSHSIG signature the same way dev-null.sh does.
 *
 * Mirrors App\Dto\SshSignature::verify() from nunomaduro/dev-null.sh, so the tests prove the
 * signatures produced by {@see \GromNaN\Notifier\Bridge\DevNull\SshSigner} are accepted.
 */
final class SshSignatureVerifier
{
    public static function verify(string $base64Signature, string $payload, string $publicKeyLine, string $namespace = 'devnull-commit'): bool
    {
        $blob = base64_decode($base64Signature, true);

        if (false === $blob || !str_starts_with($blob, 'SSHSIG')) {
            return false;
        }

        try {
            $data = substr($blob, 6);
            [$version, $publicKeyBlob, $sigNamespace, $reserved, $hashAlgorithm, $signature] = Strings::unpackSSH2('Nsssss', $data);
            [$algorithm, $rawSignature] = Strings::unpackSSH2('ss', $signature);
        } catch (\Throwable) {
            return false;
        }

        if (1 !== $version || $namespace !== $sigNamespace || base64_decode(explode(' ', $publicKeyLine)[1], true) !== $publicKeyBlob) {
            return false;
        }

        $signedData = 'SSHSIG'.Strings::packSSH2('ssss', $sigNamespace, $reserved, $hashAlgorithm, hash($hashAlgorithm, $payload, true));
        $key = PublicKeyLoader::loadPublicKey($publicKeyLine);

        if ($key instanceof EC\PublicKey) {
            return 'ssh-'.strtolower($key->getCurve()) === $algorithm && $key->verify($signedData, $rawSignature);
        }

        if ($key instanceof RSA\PublicKey) {
            return $key->withHash($hashAlgorithm)->withPadding(RSA::SIGNATURE_PKCS1)->verify($signedData, $rawSignature);
        }

        return false;
    }
}
