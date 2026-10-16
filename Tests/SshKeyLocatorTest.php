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

use GromNaN\Notifier\Bridge\DevNull\SshKeyLocator;
use PHPUnit\Framework\TestCase;

final class SshKeyLocatorTest extends TestCase
{
    use TemporaryDirectoryTrait;

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectories();
    }

    public function testPrefersTheEd25519Key()
    {
        $directory = $this->createSshDirectory(['id_rsa', 'id_ed25519']);

        $this->assertSame($directory.'/id_ed25519', (new SshKeyLocator($directory))->locate());
    }

    public function testFallsBackToTheRsaKey()
    {
        $directory = $this->createSshDirectory(['id_rsa']);

        $this->assertSame($directory.'/id_rsa', (new SshKeyLocator($directory))->locate());
    }

    public function testReturnsNullWhenTheDirectoryHasNoDefaultKey()
    {
        $directory = $this->createSshDirectory([]);

        $this->assertNull((new SshKeyLocator($directory))->locate());
    }

    public function testIgnoresKeyTypesThatCannotBeUsedForSigning()
    {
        $directory = $this->createSshDirectory(['id_ecdsa', 'id_dsa', 'id_ed25519.pub']);

        $this->assertNull((new SshKeyLocator($directory))->locate());
    }

    public function testReturnsNullWhenTheDirectoryIsMissing()
    {
        $directory = $this->createSshDirectory([]);
        rmdir($directory);

        $this->assertNull((new SshKeyLocator($directory))->locate());
    }

    public function testReturnsNullWithoutHomeDirectory()
    {
        $this->assertNull((new SshKeyLocator(''))->locate());
    }

    public function testFromEnvironmentReadsTheHomeDirectory()
    {
        $directory = $this->createSshDirectory(['id_ed25519']);

        $previousHome = getenv('HOME');
        putenv('HOME='.\dirname($directory));

        try {
            $this->assertSame($directory.'/id_ed25519', SshKeyLocator::fromEnvironment()->locate());
        } finally {
            false === $previousHome ? putenv('HOME') : putenv('HOME='.$previousHome);
        }
    }

    /**
     * @param list<string> $files
     */
    private function createSshDirectory(array $files): string
    {
        $directory = $this->createTemporaryDirectory().'/.ssh';
        mkdir($directory, 0700);

        foreach ($files as $file) {
            touch($directory.'/'.$file);
        }

        return $directory;
    }
}
