<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Migrations;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final readonly class MigrationLocator
{
    /**
     * @param  list<string>  $paths
     * @return array<non-empty-string, string>
     */
    public function files(array $paths): array
    {
        return array_map(
            fn (SplFileInfo $file): string => $file->getRealPath(),
            iterator_to_array(
                Finder::create()
                    ->files()
                    ->ignoreVCS(true)
                    ->name('*.php')
                    ->in($paths)
                    ->sortByName()
                    ->getIterator()
            )
        );
    }
}
