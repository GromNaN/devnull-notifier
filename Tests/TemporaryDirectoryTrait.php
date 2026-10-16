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

/**
 * Creates temporary directories that are removed when the test ends.
 */
trait TemporaryDirectoryTrait
{
    /**
     * @var list<string>
     */
    private array $temporaryDirectories = [];

    protected function tearDownTemporaryDirectories(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            self::removeDirectory($directory);
        }

        $this->temporaryDirectories = [];
    }

    private function createTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/devnull-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $path) {
            $path->isDir() ? self::removeDirectory($path->getPathname()) : unlink($path->getPathname());
        }

        rmdir($directory);
    }
}
