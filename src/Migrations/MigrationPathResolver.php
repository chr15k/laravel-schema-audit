<?php

namespace Chr15k\SchemaAudit\Migrations;

final readonly class MigrationPathResolver
{
    /**
     * @param  list<string>  $cliPaths
     * @return list<string>
     */
    public function resolve(array $cliPaths): array
    {
        $paths = array_merge(
            config('schema-audit.paths', []),
            $cliPaths
        );

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
