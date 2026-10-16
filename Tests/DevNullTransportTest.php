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

use GromNaN\Notifier\Bridge\DevNull\DevNullOptions;
use GromNaN\Notifier\Bridge\DevNull\DevNullTransport;
use GromNaN\Notifier\Bridge\DevNull\SshSigner;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DevNullTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): DevNullTransport
    {
        return new DevNullTransport(new SshSigner(__DIR__.'/Fixtures/id_ed25519'), true, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['devnull://dev-null.sh', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello world!')];
        yield [new ChatMessage('Hello world!', new DevNullOptions())];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testSend()
    {
        $publicKey = file_get_contents(__DIR__.'/Fixtures/id_ed25519.pub');

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($publicKey): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://dev-null.sh/api/cli/commits', $url);

            [$timestamp, $nonce, $replyTo, $message] = $this->assertCommitPayload($options['body']);
            $this->assertMatchesRegularExpression('/^\d{10}$/', $timestamp);
            $this->assertSame('', $replyTo);
            $this->assertSame('Hello world!', $message);

            $signature = $this->signatureHeader($options);
            $this->assertTrue(SshSignatureVerifier::verify($signature, $options['body'], $publicKey));

            return new MockResponse('Committed: https://dev-null.sh/@gromnan/commits/abc123', ['http_code' => 201]);
        });

        $sentMessage = self::createTransport($client)->send(new ChatMessage('Hello world!'));

        $this->assertSame('https://dev-null.sh/@gromnan/commits/abc123', $sentMessage->getMessageId());
    }

    public function testSendReply()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options = []): ResponseInterface {
            [, , $replyTo] = $this->assertCommitPayload($options['body']);
            $this->assertSame('abc123', $replyTo);

            return new MockResponse('Committed: https://dev-null.sh/@gromnan/commits/def456', ['http_code' => 201]);
        });

        $options = (new DevNullOptions())->replyTo('abc123');
        $sentMessage = self::createTransport($client)->send(new ChatMessage('Agreed!', $options));

        $this->assertSame('https://dev-null.sh/@gromnan/commits/def456', $sentMessage->getMessageId());
    }

    public function testSendThrowsOnHttpError()
    {
        $client = new MockHttpClient(new MockResponse('Invalid signature.', ['http_code' => 401]));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to commit to dev-null.sh: "Invalid signature.".');
        self::createTransport($client)->send(new ChatMessage('Hello world!'));
    }

    public function testSendThrowsOnBodyTooShort()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A dev-null.sh commit body must be between 3 and 280 characters, 2 given.');
        self::createTransport()->send(new ChatMessage('Hi'));
    }

    public function testSupportsReturnsFalseForOtherOptions()
    {
        $this->assertFalse(self::createTransport()->supports(new ChatMessage('Hello world!', new DummyOptions())));
    }

    public function testCommitBodyHasNoTrailingNewline()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options = []): ResponseInterface {
            $this->assertStringEndsWith("\nHello world!", $options['body']);
            $this->assertStringEndsNotWith("\n", $options['body']);

            return new MockResponse('Committed: https://dev-null.sh/@gromnan/commits/abc123', ['http_code' => 201]);
        });

        self::createTransport($client)->send(new ChatMessage('Hello world!'));
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function assertCommitPayload(mixed $body): array
    {
        $this->assertIsString($body);

        $parts = explode("\n", $body, 4);
        $this->assertCount(4, $parts);
        [$timestamp, $nonce, $replyTo, $message] = $parts;

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $nonce);

        return [$timestamp, $nonce, $replyTo, $message];
    }

    private function signatureHeader(array $options): string
    {
        $header = $options['normalized_headers']['x-ssh-signature'][0] ?? null;
        $this->assertNotNull($header, 'The X-SSH-Signature header is missing.');

        return trim(substr($header, \strlen('X-SSH-Signature:')));
    }
}
