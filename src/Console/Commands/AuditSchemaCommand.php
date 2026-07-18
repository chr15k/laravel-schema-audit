<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Console\Commands;

use Chr15k\SchemaAudit\Schema\SchemaBuilder;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * php artisan schema:audit
 *
 * Phase 1 milestone command: folds every migration into final per-table
 * schema state and prints it as JSON. No rules, no query analysis yet —
 * this exists so the schema-folding logic can be verified against a real
 * app before anything is built on top of it.
 */
final class AuditSchemaCommand extends Command
{
    protected $signature = 'schema:audit {--path=database/migrations}';

    protected $description = 'Fold all migrations into final per-table schema state and print as JSON';

    public function handle(SchemaBuilder $builder): int
    {
        $path = $this->resolvePath();

        $tables = $builder->buildFromDirectory($path);

        $output = array_map(
            fn (TableSchema $table): array => $table->toArray(),
            array_values($tables)
        );

        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function resolvePath(): string
    {
        $path = $this->option('path');

        if (! is_string($path)) {
            throw new RuntimeException(
                'The --path option must be a string, got '.get_debug_type($path).'.'
            );
        }

        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;

        return $isAbsolute ? $path : base_path($path);
    }
}
