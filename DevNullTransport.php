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

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Posts a commit to dev-null.sh, signed with the author's SSH key.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class DevNullTransport extends AbstractTransport
{
    protected const HOST = 'dev-null.sh';

    public function __construct(
        private SshSigner $signer,
        private bool $ssl = true,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('devnull://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof DevNullOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $body = $message->getSubject();

        if (($length = mb_strlen($body)) < 3 || $length > 280) {
            throw new LogicException(\sprintf('A dev-null.sh commit body must be between 3 and 280 characters, %d given.', $length));
        }

        $payload = implode("\n", [
            time(),
            bin2hex(random_bytes(16)),
            $options['reply_to'] ?? '',
            $body,
        ]);

        $endpoint = \sprintf('%s://%s/api/cli/commits', $this->ssl ? 'https' : 'http', $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-SSH-Signature' => $this->signer->sign($payload),
            ],
            'body' => $payload,
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $e) {
            throw new TransportException('Could not reach the dev-null.sh server.', $response, 0, $e);
        }

        if (200 > $statusCode || 300 <= $statusCode) {
            throw new TransportException(\sprintf('Unable to commit to dev-null.sh: "%s".', trim($content)), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);

        if (preg_match('#https?://\S+#', $content, $matches)) {
            $sentMessage->setMessageId($matches[0]);
        }

        return $sentMessage;
    }
}
