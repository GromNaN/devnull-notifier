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

use GromNaN\Notifier\Bridge\DevNull\DevNullTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class DevNullTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use MissingRequiredOptionTestTrait;

    private const KEY = '?private_key=%2Fpath%2Fto%2Fid_ed25519';

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
}
