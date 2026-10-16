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

use GromNaN\Notifier\Bridge\DevNull\DevNullOptions;
use PHPUnit\Framework\TestCase;

final class DevNullOptionsTest extends TestCase
{
    public function testToArray()
    {
        $options = new DevNullOptions();

        $this->assertSame([], $options->toArray());
        $this->assertNull($options->getRecipientId());

        $this->assertSame(['reply_to' => 'abc123'], $options->replyTo('abc123')->toArray());
    }
}
