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

/**
 * Finds the default SSH private key of the current user.
 *
 * Used when the DSN does not provide a "private_key" option, so that a machine already
 * set up for the devnull CLI works without any configuration.
 *
 * Only the key types {@see SshSigner} can sign with are looked up: Ed25519 first, then RSA.
 * Keys held by an ssh-agent are not usable, because the private key material stays in the
 * agent and this package signs in pure PHP.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class SshKeyLocator
{
    /**
     * Default identity file names, in the order they are tried.
     */
    public const DEFAULT_KEY_NAMES = ['id_ed25519', 'id_rsa'];

    /**
     * @param string $directory Directory holding the SSH keys, usually "~/.ssh".
     */
    public function __construct(
        private string $directory,
    ) {
    }

    /**
     * Builds a locator for the SSH directory of the user running the process.
     */
    public static function fromEnvironment(): self
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: null;

        return new self(null === $home ? '' : $home.\DIRECTORY_SEPARATOR.'.ssh');
    }

    /**
     * Returns the path of the first default key that exists, or null when there is none.
     */
    public function locate(): ?string
    {
        if ('' === $this->directory) {
            return null;
        }

        foreach (self::DEFAULT_KEY_NAMES as $name) {
            $path = $this->directory.\DIRECTORY_SEPARATOR.$name;

            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
