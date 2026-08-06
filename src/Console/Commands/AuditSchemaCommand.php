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
use Throwable;

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

        $resolvedPaths = $this->resolvePathsFromOption();

        try {
            $files = $this->locator->files($resolvedPaths);
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }

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

    /**
     * @return list<string>
     */
    private function resolvePathsFromOption(): array
    {
        $paths = (array) $this->option('path');

        $paths = array_filter(
            $paths,
            static fn (mixed $path): bool => is_string($path) && $path !== '',
        );

        return $this->paths->resolve(array_values($paths));
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
        $this->line($audit->toPrettyJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

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

                $this->renderSeparator();
                $this->newLine();
                $this->line(sprintf(
                    '  <%s> %s </> <fg=cyan>(%d %s)</>',
                    $worst->colorTag(),
                    $table,
                    $count,
                    $grammar
                ));
                $items->each($this->renderFinding(...));
            });
    }

    private function renderSeparator(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>  '.str_repeat('=', $this->terminalContentWidth()).'</>');
    }

    private function renderFinding(Finding $finding): void
    {
        $column = is_string($finding->columns)
            ? $finding->columns
            : implode(', ', $finding->columns ?? []);

        $severity = $finding->severity;

        $this->newLine();
        $this->components->twoColumnDetail(
            sprintf(
                '  <fg=%s>%s</> %s',
                $severity->color(),
                $severity->glyph(),
                (string) $finding,
            ),
            sprintf('<fg=default;options=bold>%s</>', $column ?: 'N/A')
        );

        $this->renderWrappedArrow($finding->message);

        $this->renderLocation($finding);

        $this->renderRelated($finding);

        $this->renderConditional($finding);
    }

    private function renderWrappedArrow(string $text, string $color = 'cyan', bool $bold = false): void
    {
        $this->line(sprintf(
            '    <fg=%s%s>↳ %s</>',
            $color,
            $bold ? ';options=bold' : '',
            wordwrap($text, $this->terminalContentWidth() - 6, "\n      ")
        ));
    }

    private function terminalContentWidth(): int
    {
        $terminalWidth = max(1, (new Terminal)->getWidth());

        return max(1, min($terminalWidth - 4, 146));
    }

    private function renderLocation(Finding $finding): void
    {
        if (! $finding->location instanceof SourceLocation) {
            return;
        }

        $this->newLine();

        $this->line('    <fg=cyan>Location:</>');

        $this->renderWrappedArrow(sprintf('<fg=default>%s</>', $finding->location->relative()));
    }

    private function renderRelated(Finding $finding): void
    {
        foreach ($finding->related as $related) {
            if ($related->location() === null) {
                continue;
            }

            $this->newLine();
            $this->line('    <fg=cyan>Related:</>');

            $this->renderWrappedArrow(sprintf('<fg=default>%s</>', $related->location()->relative()));
        }
    }

    private function renderConditional(Finding $finding): void
    {
        if (! $finding->guard()?->impliesConditional()) {
            return;
        }

        $this->newLine();

        $this->line(
            '    <fg=yellow>Note:</>'
        );

        $this->renderWrappedArrow(sprintf(
            '<fg=yellow>%s</>',
            'This finding may be a false positive because conditional migration logic prevents the final schema from being determined with certainty.'
        ));
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

        $this->newLine(2);
        $this->components->twoColumnDetail(
            '<fg=red;options=bold>FAIL</>',
            sprintf('<fg=default>%ss</>', $duration)
        );
        $this->renderWrappedArrow(sprintf('%d schema %s found', $count, $grammar), 'red', true);
        $this->newLine();
    }
}
