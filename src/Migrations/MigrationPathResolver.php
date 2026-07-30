<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Migrations;

use Chr15k\SchemaAudit\Support\Config;

final readonly class MigrationPathResolver
{
    public function __construct(private Config $config) {}

    /**
     * Provide paths to resolve; defaults to configured paths.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public function resolve(array $paths = []): array
    {
        $paths = $paths !== [] ? $paths : $this->config->paths();

        return array_values(array_unique(
            array_map($this->absolute(...), $paths)
        ));
    }

    private function absolute(string $path): string
    {
        return $this->isAbsolute($path) ? $path : base_path($path);
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }
}
