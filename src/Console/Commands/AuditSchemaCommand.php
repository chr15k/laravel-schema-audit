<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Console\Commands;

use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Migrations\MigrationLocator;
use Chr15k\SchemaAudit\Migrations\MigrationPathResolver;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SourceLocation;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\SchemaBuilder;
use Chr15k\SchemaAudit\SchemaAudit;
use Chr15k\SchemaAudit\SchemaAuditor;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Terminal;

final class AuditSchemaCommand extends Command
{
    protected $signature = 'schema:audit
        {--path=* : Migration paths to audit (overrides configured paths)}
        {--schema-only : Print the raw folded schema instead of running rules}
        {--json : Print findings as JSON instead of the styled report}';

    protected $description = 'Audit migration-declared schema for unindexed foreign keys, duplicate/redundant indexes, dangling foreign keys, mismatched foreign keys, and missing primary keys';

    public function __construct(
        private readonly SchemaAuditor $auditor,
        private readonly MigrationPathResolver $paths,
        private readonly MigrationLocator $locator,
    ) {
        parent::__construct();
    }

    public function handle(SchemaBuilder $builder): int
    {
        $start = microtime(true);

        /** @var list<string> $path */
        $path = $this->option('path');

        $files = $this->locator->files(
            $this->paths->resolve($path)
        );

        $progress = null;

        if (! $this->option('schema-only') && ! $this->option('json')) {
            $progress = $this->initProgress(count($files));
        }

        $schema = $builder->build($files, fn () => $progress?->advance());

        if ($progress instanceof ProgressBar) {
            $progress->finish();
            $this->newLine();
        }

        if ($this->option('schema-only')) {
            return $this->renderSchemaOnly($schema);
        }

        $audit = $this->auditor->audit($schema);

        if (! config('schema-audit.report_conditional_findings')) {
            $audit = $audit->withoutConditionalFindings();
        }

        $duration = $this->duration($start);

        if ($this->option('json')) {
            return $this->renderJson($audit);
        }

        return $this->renderReport($audit, $schema->tableCount(), $duration);
    }

    private function initProgress(int $max = 0): ProgressBar
    {
        $progress = $this->output->createProgressBar($max);

        $progress->setFormat('%bar%');
        $progress->setBarCharacter('.');
        $progress->setEmptyBarCharacter(' ');

        $progress->start();

        return $progress;
    }

    private function duration(float $start): string
    {
        return number_format(microtime(true) - $start, 2);
    }

    private function renderSchemaOnly(Schema $schema): int
    {
        $this->line($schema->toPrettyJson());

        return self::SUCCESS;
    }

    private function renderJson(SchemaAudit $audit): int
    {
        $this->line($audit->toPrettyJson());

        return $audit->hasIssues() ? self::FAILURE : self::SUCCESS;
    }

    private function renderReport(SchemaAudit $audit, int $tableCount, string $duration): int
    {
        $this->renderStats($audit, $tableCount);

        if ($audit->hasIssues()) {
            $this->renderFindings($audit);
            $this->renderFail($duration, $audit->count());

            return self::FAILURE;
        }

        $this->renderPass($duration);

        return self::SUCCESS;
    }

    private function renderStats(SchemaAudit $audit, int $tableCount): void
    {
        $this->newLine();
        $this->line('  <options=bold>Schema Audit Results</>');
        $this->newLine();
        $this->line(sprintf(
            '  <fg=green>%d</> tables audited    <fg=blue>%d</> rules executed    <fg=red>%d</> findings',
            $tableCount,
            count($this->auditor->rules()),
            $audit->count()
        ));
    }

    private function renderFindings(SchemaAudit $audit): void
    {
        collect($audit->findings)
            ->sortBy('table')
            ->groupBy('table')
            ->each(function (Collection $items, string $table): void {
                $count = $items->count();
                $grammar = str('issue')->plural($count);
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
            sprintf(
                '  <fg=%s>%s</> %s',
                $severity->color(),
                $severity->glyph(),
                (string) $finding,
            ),
            sprintf('<fg=default;options=bold>%s</>', $column)
        );

        $this->renderWrappedArrow($finding->message);

        $this->renderLocation($finding);

        $this->renderRelated($finding);

        $this->renderConditional($finding);
    }

    private function renderWrappedArrow(string $text): void
    {
        $width = (new Terminal)->getWidth() - 20;

        $this->line(sprintf(
            '    <fg=gray>↳ %s</>',
            wordwrap($text, $width ?: 80, "\n      ")
        ));
    }

    private function renderLocation(Finding $finding): void
    {
        if (! $finding->location instanceof SourceLocation) {
            return;
        }

        $this->newLine();

        $this->line('    <fg=gray>Location:</>');

        $this->renderWrappedArrow(sprintf('<fg=default>%s</>', $finding->location->relative()));
    }

    private function renderRelated(Finding $finding): void
    {
        foreach ($finding->related as $label => $location) {
            if (! $location) {
                continue; // @todo - fix this
            }

            $this->newLine();

            $this->line(sprintf(
                '    <fg=gray>%s:</>',
                $label,
            ));

            $this->renderWrappedArrow(sprintf('<fg=default>%s</>', $location->relative()));
        }
    }

    private function renderConditional(Finding $finding): void
    {
        if (! $finding->conditional) {
            return;
        }

        $this->newLine();

        $this->line(
            '    <fg=yellow>Note:</>'
        );

        $this->line(
            '      <fg=yellow>This finding may be a false positive because the schema is modified by conditional migration logic.</>'
        );
    }

    /**
     * @param  Collection<int, Finding>  $findings
     */
    private function worstSeverity(Collection $findings): Severity
    {
        $rank = [
            Severity::Error->value   => 0,
            Severity::Warning->value => 1,
            Severity::Info->value    => 2,
        ];

        return $findings
            ->map(fn (Finding $finding): Severity => $finding->severity)
            ->sortBy(fn (Severity $severity): int => $rank[$severity->value])
            ->firstOrFail();
    }

    private function renderPass(string $duration): void
    {
        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=green;options=bold>PASS</>',
            sprintf('<fg=default>%ss</>', $duration)
        );
        $this->newLine();
    }

    private function renderFail(string $duration, int $count): void
    {
        $grammar = str('issue')->plural($count);

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=red;options=bold>FAIL</>',
            sprintf('<fg=default>%ss</>', $duration)
        );
        $this->components->bulletList([sprintf('%d schema %s found.', $count, $grammar)]);
        $this->newLine();
    }
}
