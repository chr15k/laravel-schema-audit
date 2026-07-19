<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Console\Commands;

use Chr15k\SchemaAudit\Schema\Rules\Finding;
use Chr15k\SchemaAudit\Schema\Rules\SchemaAuditor;
use Chr15k\SchemaAudit\Schema\SchemaBuilder;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Illuminate\Console\Command;

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
        {--driver=mysql : Database driver, affects which rules apply (e.g. FK auto-indexing)}
        {--schema-only : Print the raw folded schema instead of running rules}';

    protected $description = 'Audit migration-declared schema for unindexed foreign keys, duplicate/redundant indexes, dangling foreign keys, and missing primary keys';

    public function handle(SchemaBuilder $builder): int
    {
        $path = $this->resolvePath($this->option('path'));

        $tables = $builder->buildFromDirectory($path);

        if ($this->option('schema-only')) {
            $output = array_map(fn (TableSchema $table): array => $table->toArray(), array_values($tables));
            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $findings = SchemaAuditor::withDefaultRules((string) $this->option('driver'))->audit($tables);

        $this->line(json_encode(
            array_map(fn (Finding $finding): array => $finding->toArray(), $findings),
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        ));

        return $findings === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Resolve the --path option against the app base path, unless it's
     * already absolute — lets callers (and tests) point at a directory
     * outside the app, e.g. a fixtures folder, without it being incorrectly
     * concatenated onto base_path().
     */
    private function resolvePath(string $path): string
    {
        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;

        return $isAbsolute ? $path : base_path($path);
    }
}
