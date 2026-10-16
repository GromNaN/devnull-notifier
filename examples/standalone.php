#!/usr/bin/env php
<?php

/*
 * This file is part of the gromnan/devnull-notifier package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Sends a commit to dev-null.sh with Symfony Notifier, without a Symfony application.
 *
 * Usage:
 *     php examples/standalone.php "My commit body"
 *     DEVNULL_DSN='devnull://default?private_key=%2Fhome%2Fme%2F.ssh%2Fid_ed25519' \
 *         php examples/standalone.php "My commit body"
 *
 * The private key must be one of the SSH keys of your GitHub account. When the DSN has no
 * "private_key" option, the default key of the current user is used: ~/.ssh/id_ed25519
 * first, then ~/.ssh/id_rsa.
 */

use GromNaN\Notifier\Bridge\DevNull\DevNullOptions;
use GromNaN\Notifier\Bridge\DevNull\DevNullTransportFactory;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Transport;

require __DIR__.'/../vendor/autoload.php';

$body = $argv[1] ?? 'Hello from Symfony Notifier!';
$dsn = getenv('DEVNULL_DSN') ?: 'devnull://default';

// The Transport class only knows the bridges shipped with symfony/notifier, so it is built
// with an explicit factory list. Adding DevNullTransportFactory teaches it "devnull://".
// Pass an EventDispatcherInterface or an HttpClientInterface to the factory constructor if
// you need to hook into the HTTP calls.
$transportFactory = new Transport([
    new DevNullTransportFactory(),
]);

// fromString() also understands failover ("||") and round-robin ("&&") DSNs, so the same
// call works with several transports at once.
$transport = $transportFactory->fromString($dsn);

// Chatter is the entry point of the Notifier for chat messages. Pass a MessageBusInterface
// as second argument to dispatch messages asynchronously instead of sending them inline.
$chatter = new Chatter($transport);

$options = new DevNullOptions();
// Uncomment to reply to an existing commit, using its hash or its link:
// $options = $options->replyTo('abc123');

$message = new ChatMessage($body, $options);

try {
    $sentMessage = $chatter->send($message);
} catch (TransportExceptionInterface $e) {
    fwrite(\STDERR, 'The commit was rejected: '.$e->getMessage()."\n");
    exit(1);
}

// The message ID is the link of the commit on dev-null.sh.
echo $sentMessage?->getMessageId(), "\n";
