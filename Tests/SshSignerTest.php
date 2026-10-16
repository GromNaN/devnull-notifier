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

use GromNaN\Notifier\Bridge\DevNull\SshSigner;
use phpseclib4\Common\Functions\Strings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\LogicException;

final class SshSignerTest extends TestCase
{
    private const PAYLOAD = "1750000000\n0123456789abcdef0123456789abcdef\n\nHello world!";

    // Signatures of PAYLOAD with the fixture keys, byte-identical to `ssh-keygen -Y sign`.
    private const ED25519_SIGNATURE = 'U1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgHh5LESR/Vzta2OF5WDmnVeYVW0egWSL6poKvhct4SKAAAAAOZGV2bnVsbC1jb21taXQAAAAAAAAABnNoYTUxMgAAAFMAAAALc3NoLWVkMjU1MTkAAABAjJwo+DfLySNtl4iM/E4w1IVysECCd3Ob6q9hByRFFJnG4YiVv4NjE+NLM5lPrzL0c5vqt0zCzna+yBXTkX/rBg==';
    private const RSA_SIGNATURE = 'U1NIU0lHAAAAAQAAARcAAAAHc3NoLXJzYQAAAAMBAAEAAAEBAMvgALLdK6eU/W01jD1TqBCY443gQFxOTomDxE62LxFNRSkyH2vshEim4ov9bFk0oaZyAnFvNnTOxR567eO33kcSMVor7utYu3vwkyzQr7xPJL7Ucr3fIFfPx/BSkFlE0l0syVPnZ/3tyqgrCBSb1d47lfnaVgJBb5XSSPqZ2BOUTjGvW7eEptBr2/6kGsKoGzffbBkxgJ9/FR/xFa7xIvtnFHRe+lJxDdJmLhpia5s0PPBbU2/dfvKBxo2qut8FKqk68Ah75GMsud4b0mu/M/ynHpiY5DnPqwXG8k4yvvOyz/hQgYUa9fetNPifGEpnIzlP0m/1oq7UXkzAa5VRdrcAAAAOZGV2bnVsbC1jb21taXQAAAAAAAAABnNoYTUxMgAAARQAAAAMcnNhLXNoYTItNTEyAAABAGCLJUx9N8UxwlS/s+r2cTMazrtCZKco2ztVfutRiffuXk8Y9107onkoCteeSGfu1MpoMqZCc9m2W2zQHqU0zrGlt3btfkvl/3iwc00BP9avf/gbmiBsOiaNLopSYmbvuqql88EW0VR8+0lwj6pI4kEHGJ0KoWDgtPWAJYaiO0/2o4VO8Aogcqcw+l4UvZYzRl3hmKbq3UQy//vw4GrdmH/QzS4+fdh1IDQYBmnH6x/t31p+Aio4R5vpVIJxosBqUQQ1U2L4LaDLh9jTipLOx52CubRjRPkuBuJeC7mJ8DqVGlgT7NGzfrgYhE+BM3ydOMnwep7DG+hKwWJudVQPOnk=';

    public function testSignsWithEd25519Key()
    {
        $signature = (new SshSigner(self::fixture('id_ed25519')))->sign(self::PAYLOAD);

        $this->assertSame(self::ED25519_SIGNATURE, $signature);
        $this->assertTrue(SshSignatureVerifier::verify($signature, self::PAYLOAD, self::publicKey('id_ed25519')));
    }

    public function testSignsWithEncryptedEd25519Key()
    {
        $signature = (new SshSigner(self::fixture('id_ed25519_passphrase'), 'secret'))->sign(self::PAYLOAD);

        $this->assertTrue(SshSignatureVerifier::verify($signature, self::PAYLOAD, self::publicKey('id_ed25519_passphrase')));
    }

    public function testSignsWithRsaKey()
    {
        $signature = (new SshSigner(self::fixture('id_rsa')))->sign(self::PAYLOAD);

        $this->assertSame(self::RSA_SIGNATURE, $signature);
        $this->assertTrue(SshSignatureVerifier::verify($signature, self::PAYLOAD, self::publicKey('id_rsa')));
    }

    public function testSignatureHasTheExpectedStructure()
    {
        $blob = base64_decode((new SshSigner(self::fixture('id_ed25519')))->sign(self::PAYLOAD), true);

        $this->assertStringStartsWith('SSHSIG', $blob);

        $data = substr($blob, 6);
        [$version, $publicKey, $namespace, $reserved, $hashAlgorithm, $signature] = Strings::unpackSSH2('Nsssss', $data);
        [$algorithm] = Strings::unpackSSH2('ss', $signature);

        $this->assertSame(1, $version);
        $this->assertSame('devnull-commit', $namespace);
        $this->assertSame('', $reserved);
        $this->assertSame('sha512', $hashAlgorithm);
        $this->assertSame('ssh-ed25519', $algorithm);
        $this->assertSame(base64_decode(explode(' ', self::publicKey('id_ed25519'))[1], true), $publicKey);
    }

    public function testUsesACustomNamespace()
    {
        $signer = new SshSigner(self::fixture('id_ed25519'), null, 'other-namespace');

        $this->assertFalse(SshSignatureVerifier::verify($signer->sign(self::PAYLOAD), self::PAYLOAD, self::publicKey('id_ed25519')));
    }

    public function testThrowsWhenKeyFileIsMissing()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unable to read the SSH private key from');

        (new SshSigner('/does/not/exist'))->sign(self::PAYLOAD);
    }

    public function testThrowsWhenPassphraseIsWrong()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unable to load the SSH private key from');

        (new SshSigner(self::fixture('id_ed25519_passphrase'), 'wrong'))->sign(self::PAYLOAD);
    }

    private static function fixture(string $name): string
    {
        return __DIR__.'/Fixtures/'.$name;
    }

    private static function publicKey(string $name): string
    {
        return file_get_contents(__DIR__.'/Fixtures/'.$name.'.pub');
    }
}
