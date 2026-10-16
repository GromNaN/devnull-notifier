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

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * Message options that do not belong to the dev-null.sh transport.
 */
final class DummyOptions implements MessageOptionsInterface
{
    public function toArray(): array
    {
        return [];
    }

    public function getRecipientId(): ?string
    {
        return null;
    }
}
