<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Console\Commands;

use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\SchemaAuditor;
use Chr15k\SchemaAudit\SchemaBuilder;
use Chr15k\SchemaAudit\TableSchema;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Chr15k\SchemaAudit\ValueObjects\Schema;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

final class AuditSchemaCommand extends Command
{
    protected $signature = 'schema:audit
        {--path=database/migrations : Directory to scan for migration files}
        {--schema-only : Print the raw folded schema instead of running rules}
        {--json : Print findings as JSON instead of the styled report}';

    protected $description = 'Audit migration-declared schema for unindexed foreign keys, duplicate/redundant indexes, dangling foreign keys, mismatched foreign keys, and missing primary keys';

    public function __construct(private readonly SchemaAuditor $auditor)
    {
        parent::__construct();
    }

    public function handle(SchemaBuilder $builder): int
    {
        $start = microtime(true);

        $schema = $builder->buildFromDirectory($this->resolvePath());

        if ($this->option('schema-only')) {
            return $this->renderFindingschemaOnly($schema);
        }

        $findings = $this->auditor->audit($schema);
        $duration = $this->duration($start);

        if ($this->option('json')) {
            return $this->renderJson($findings);
        }

        return $this->renderReport($findings, $schema->tableCount(), $duration);
    }

    private function duration(float $start): string
    {
        return number_format(microtime(true) - $start, 2);
    }

    private function renderFindingschemaOnly(Schema $schema): int
    {
        $output = collect($schema->tables())
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

        $this->renderFindings($findings, $tableCount);
        $this->renderFail($duration, count($findings));

        return self::FAILURE;
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function renderFindings(array $findings, int $tableCount): void
    {
        $this->newLine();
        $this->line('  <options=bold>Schema Audit Results</>');
        $this->newLine();
        $this->line(sprintf(
            '  <fg=green>%d</> tables audited    <fg=blue>%d</> rules executed    <fg=red>%d</> findings',
            $tableCount,
            $this->auditor->ruleCount(),
            count($findings)
        ));

        collect($findings)
            ->sortBy('table')
            ->groupBy('table')
            ->each(function (Collection $items, string $table): void {
                $count = $items->count();
                $grammar = Str::plural('issue', $count);
                $worst = $this->worstSeverity($items);

                $this->newLine();
                $this->line(sprintf(
                    '  <fg=%s;options=bold>%s</> <fg=gray>(%d %s)</>',
                    $worst->color(),
                    $table,
                    $count,
                    $grammar
                ));

                $items->each($this->renderFinding(...));
            });
    }

    private function renderFinding(Finding $finding): void
    {
        $column = $finding->column ?? '—';
        $severity = $finding->severity;

        $this->components->twoColumnDetail(
            sprintf('  <fg=%s>%s</> %s', $severity->color(), $severity->glyph(), (string) $finding),
            sprintf('<fg=gray>%s</>', $column)
        );

        $this->line(sprintf('    <fg=gray>↳ %s</>', $finding->message));
    }

    /**
     * @param  Collection<int, Finding>  $findings
     */
    private function worstSeverity(Collection $findings): Severity
    {
        $rank = [Severity::Error->value => 0, Severity::Warning->value => 1, Severity::Info->value => 2];

        return $findings
            ->map(fn (Finding $finding): Severity => $finding->severity)
            ->sortBy(fn (Severity $severity): int => $rank[$severity->value])
            ->first();
    }

    private function renderPass(string $duration, int $tableCount): void
    {
        $this->newLine();
        $this->components->info(sprintf('No schema issues found across %d table(s).', $tableCount));
        $this->line(sprintf('  <fg=gray>Duration: %ss</>', $duration));
        $this->newLine();
    }

    private function renderFail(string $duration, int $count): void
    {
        $grammar = Str::plural('issue', $count);

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=red;options=bold>FAIL</>',
            sprintf('<fg=gray>%ss</>', $duration)
        );
        $this->components->bulletList([sprintf('%d schema %s found.', $count, $grammar)]);
        $this->newLine();
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
