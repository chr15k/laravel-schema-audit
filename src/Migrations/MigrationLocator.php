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
        return array_values(array_filter(
            array_map(
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
            ),
            fn (string $file): bool => $this->isMigration($file)
        ));
    }

    private function isMigration(string $file): bool
    {
        return str_contains(file_get_contents($file), 'Schema::');
    }
}
