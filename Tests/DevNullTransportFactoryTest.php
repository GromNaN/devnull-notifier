<?php

/*
 * This file is part of the gromnan/devnull-notifier package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\Notifier\Bridge\DevNull\Tests;

use GromNaN\Notifier\Bridge\DevNull\DevNullTransportFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Exception\MissingRequiredOptionException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DevNullTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use MissingRequiredOptionTestTrait;
    use TemporaryDirectoryTrait;

    private const KEY = '?private_key=%2Fpath%2Fto%2Fid_ed25519';

    private string|false $originalHome = false;

    protected function setUp(): void
    {
        // The factory falls back to the default SSH key of the user. Hide the real home
        // directory so that the tests do not depend on the machine they run on.
        $this->originalHome = getenv('HOME');
        putenv('HOME');
        putenv('USERPROFILE');
    }

    protected function tearDown(): void
    {
        false === $this->originalHome ? putenv('HOME') : putenv('HOME='.$this->originalHome);

        $this->tearDownTemporaryDirectories();
    }

    public function createFactory(): TransportFactoryInterface
    {
        return new DevNullTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['devnull://dev-null.sh', 'devnull://default'.self::KEY];
        yield ['devnull://example.com', 'devnull://example.com'.self::KEY];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'devnull://default'.self::KEY];
        yield [false, 'somethingelse://default'.self::KEY];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield ['devnull://default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingelse://default'.self::KEY];
    }

    public function testCreateThrowsWhenNoPrivateKeyIsAvailable()
    {
        $this->useHomeDirectory([]);

        $this->expectException(MissingRequiredOptionException::class);
        $this->expectExceptionMessage('The option "private_key" is required but missing.');

        (new DevNullTransportFactory())->create(new Dsn('devnull://default'));
    }

    public function testCreateFallsBackToTheDefaultSshKey()
    {
        $this->useHomeDirectory(['id_ed25519' => file_get_contents(__DIR__.'/Fixtures/id_ed25519')]);

        $client = $this->assertingClient(__DIR__.'/Fixtures/id_ed25519.pub');

        $transport = (new DevNullTransportFactory(null, $client))->create(new Dsn('devnull://default'));
        $sentMessage = $transport->send(new ChatMessage('Hello world!'));

        $this->assertSame('https://dev-null.sh/@gromnan/commits/abc123', $sentMessage->getMessageId());
    }

    public function testCreatePrefersThePrivateKeyOptionOverTheDefaultSshKey()
    {
        // The default key cannot be loaded, so a successful send proves the option won.
        $this->useHomeDirectory(['id_ed25519' => 'not a key']);

        $client = $this->assertingClient(__DIR__.'/Fixtures/id_ed25519.pub');

        $dsn = new Dsn('devnull://default?private_key='.rawurlencode(__DIR__.'/Fixtures/id_ed25519'));
        $transport = (new DevNullTransportFactory(null, $client))->create($dsn);

        $this->assertSame('https://dev-null.sh/@gromnan/commits/abc123', $transport->send(new ChatMessage('Hello world!'))->getMessageId());
    }

    /**
     * Returns a client that checks the signature of the request against the given public key.
     */
    private function assertingClient(string $publicKeyFile): MockHttpClient
    {
        $publicKey = file_get_contents($publicKeyFile);

        return new MockHttpClient(function (string $method, string $url, array $options = []) use ($publicKey): ResponseInterface {
            $header = $options['normalized_headers']['x-ssh-signature'][0] ?? null;
            $this->assertNotNull($header, 'The X-SSH-Signature header is missing.');

            $signature = trim(substr($header, \strlen('X-SSH-Signature:')));
            $this->assertTrue(SshSignatureVerifier::verify($signature, $options['body'], $publicKey));

            return new MockResponse('Committed: https://dev-null.sh/@gromnan/commits/abc123', ['http_code' => 201]);
        });
    }

    /**
     * Points the HOME environment variable at a fresh directory holding the given files.
     *
     * @param array<string, string> $files File name to content.
     */
    private function useHomeDirectory(array $files): void
    {
        $home = $this->createTemporaryDirectory();
        mkdir($home.'/.ssh', 0700);

        foreach ($files as $name => $content) {
            file_put_contents($home.'/.ssh/'.$name, $content);
        }

        putenv('HOME='.$home);
    }
}
