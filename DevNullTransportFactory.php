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

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Jérôme Tamarelle <jerome.tamarelle@mongodb.com>
 */
final class DevNullTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): DevNullTransport
    {
        if ('devnull' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'devnull', $this->getSupportedSchemes());
        }

        $signer = new SshSigner(
            (string) $dsn->getRequiredOption('private_key'),
            $dsn->getOption('passphrase'),
            $dsn->getOption('namespace', SshSigner::NAMESPACE),
        );

        return (new DevNullTransport($signer, $dsn->getBooleanOption('ssl', true), $this->client, $this->dispatcher))
            ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
            ->setPort($dsn->getPort());
    }

    protected function getSupportedSchemes(): array
    {
        return ['devnull'];
    }
}
