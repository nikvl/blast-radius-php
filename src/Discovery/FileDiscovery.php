<?php

declare(strict_types=1);

namespace PhpUmlGenerator\Discovery;

use Symfony\Component\Finder\Finder;

class FileDiscovery
{
    private const DEFAULT_EXCLUDES = ['vendor', 'storage', 'bootstrap/cache', 'node_modules'];

    /** @return string[] */
    public function findPhpFiles(string $path, array $extraExcludes = []): array
    {
        $finder = (new Finder())
            ->files()
            ->in($path)
            ->name('*.php')
            ->notPath(array_merge(self::DEFAULT_EXCLUDES, $extraExcludes));

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }
}
