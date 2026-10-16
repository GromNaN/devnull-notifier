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

use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * A message that the dev-null.sh transport does not support.
 */
final class DummyMessage implements MessageInterface
{
    public function getRecipientId(): ?string
    {
        return null;
    }

    public function getSubject(): string
    {
        return 'dummy';
    }

    public function getOptions(): ?MessageOptionsInterface
    {
        return null;
    }

    public function getTransport(): ?string
    {
        return null;
    }
}
