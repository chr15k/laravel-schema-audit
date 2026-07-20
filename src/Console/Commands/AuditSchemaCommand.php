<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Console\Commands;

use Chr15k\SchemaAudit\SchemaAuditor;
use Chr15k\SchemaAudit\SchemaBuilder;
use Chr15k\SchemaAudit\TableSchema;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * php artisan schema:audit
 *
 * By default, folds every migration into final per-table schema state and
 * runs the built-in rule set (unindexed FKs, duplicate/redundant indexes,
 * dangling FKs, missing primary keys) against it, printing findings as a
 * styled report. Pass --schema-only to print the raw folded schema
 * instead, with no rules applied — useful for debugging the folding step
 * itself. Pass --json for machine-readable findings output (CI-friendly).
 */
final class AuditSchemaCommand extends Command
{
    protected $signature = 'schema:audit
        {--path=database/migrations : Directory to scan for migration files}
        {--schema-only : Print the raw folded schema instead of running rules}
        {--json : Print findings as JSON instead of the styled report}';

    protected $description = 'Audit migration-declared schema for unindexed foreign keys, duplicate/redundant indexes, dangling foreign keys, and missing primary keys';

    public function __construct(private readonly SchemaAuditor $auditor)
    {
        parent::__construct();
    }

    public function handle(SchemaBuilder $builder): int
    {
        $start = microtime(true);

        $tables = $builder->buildFromDirectory($this->resolvePath());

        if ($this->option('schema-only')) {
            return $this->renderSchemaOnly($tables);
        }

        $findings = $this->auditor->audit($tables);
        $duration = $this->duration($start);

        if ($this->option('json')) {
            return $this->renderJson($findings);
        }

        return $this->renderReport($findings, count($tables), $duration);
    }

    private function duration(float $start): string
    {
        return number_format(microtime(true) - $start, 2);
    }

    /**
     * @param  array<string, TableSchema>  $tables
     */
    private function renderSchemaOnly(array $tables): int
    {
        $output = collect($tables)
            ->values()
            ->map(fn (TableSchema $table): array => $table->toArray())
            ->all();

        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function renderJson(array $findings): int
    {
        $output = collect($findings)
            ->map(fn (Finding $finding): array => $finding->toArray())
            ->all();

        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $output === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function renderReport(array $findings, int $tableCount, string $duration): int
    {
        if ($findings === []) {
            $this->renderPass($duration, $tableCount);

            return self::SUCCESS;
        }

        $this->renderFindings($findings);
        $this->renderFail($duration, count($findings));

        return self::FAILURE;
    }

    private function renderPass(string $duration, int $tableCount): void
    {
        $this->info(sprintf('No schema issues found across %d table(s). Duration: %ss', $tableCount, $duration));
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function renderFindings(array $findings): void
    {
        collect($findings)
            ->sortBy('table')
            ->groupBy('table')
            ->each->each(function (Finding $finding): void {
                $this->components->error($finding->table);
                $this->comment('   '.$this->humanizeRule($finding->rule));
                $this->newLine();
                $this->comment('   '.$finding->message);
                $this->newLine(2);
            });
    }

    private function renderFail(string $duration, int $count): void
    {
        $grammar = Str::plural('issue', $count);

        $this->components->error(sprintf('%d schema %s found. Duration: %ss', $count, $grammar, $duration));
        $this->components->info('Run with --json for machine-readable output, or --schema-only to inspect the raw folded schema.');
    }

    /**
     * Turns a snake_case rule identifier like 'unindexed_foreign_key' into
     * a readable label like 'Unindexed Foreign Key' for the report header.
     */
    private function humanizeRule(string $rule): string
    {
        return str(str_replace('_', ' ', $rule))->title()->toString();
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
