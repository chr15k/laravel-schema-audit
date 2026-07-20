<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Console\Commands;

use Chr15k\SchemaAudit\Data\Finding;
use Chr15k\SchemaAudit\SchemaAuditor;
use Chr15k\SchemaAudit\SchemaBuilder;
use Chr15k\SchemaAudit\TableSchema;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * php artisan schema:audit
 *
 * By default, folds every migration into final per-table schema state and
 * runs the built-in rule set (unindexed FKs, duplicate/redundant indexes,
 * dangling FKs, missing primary keys) against it, printing findings as
 * JSON. Pass --schema-only to print the raw folded schema instead, with
 * no rules applied — useful for debugging the folding step itself.
 */
final class AuditSchemaCommand extends Command
{
    protected $signature = 'schema:audit
        {--path=database/migrations : Directory to scan for migration files}
        {--schema-only : Print the raw folded schema instead of running rules}';

    protected $description = 'Audit migration-declared schema for unindexed foreign keys, duplicate/redundant indexes, dangling foreign keys, and missing primary keys';

    public function __construct(private readonly SchemaAuditor $auditor)
    {
        parent::__construct();
    }

    public function handle(SchemaBuilder $builder): int
    {
        $path = $this->resolvePath();

        $tables = $builder->buildFromDirectory($path);

        if ($this->option('schema-only')) {
            $output = array_map(fn (TableSchema $table): array => $table->toArray(), array_values($tables));
            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $findings = $this->auditor->audit($tables);

        $this->line(json_encode(
            array_map(fn (Finding $finding): array => $finding->toArray(), $findings),
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        ));

        return $findings === [] ? self::SUCCESS : self::FAILURE;
    }

    private function resolvePath(): string
    {
        $path = $this->option('path') ?? config('schema-audit.path');

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
