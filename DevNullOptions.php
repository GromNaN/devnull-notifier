<?php

/*
 * This file is part of the gromnan/devnull-notifier package.
 *
 * (c) Jérôme Tamarelle <jerome.tamarelle@mongodb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\Notifier\Bridge\DevNull;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Jérôme Tamarelle <jerome.tamarelle@mongodb.com>
 */
final class DevNullOptions implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * Sends the commit as a reply to an existing one.
     *
     * The reference is the commit hash shown on dev-null.sh or its link.
     *
     * @return $this
     */
    public function replyTo(string $reference): static
    {
        $this->options['reply_to'] = $reference;

        return $this;
    }
}
