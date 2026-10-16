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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Registers the dev-null.sh transport factory with the Notifier.
 *
 * @author Jérôme Tamarelle <jerome.tamarelle@mongodb.com>
 */
final class DevNullNotifierBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->register(DevNullTransportFactory::class)
            ->setAutowired(true)
            ->addTag('chatter.transport_factory');
    }
}
