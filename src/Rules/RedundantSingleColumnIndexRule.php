<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;
use Closure;

final readonly class RedundantSingleColumnIndexRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            $indexes = $table->indexes();

            $singleColumnIndexes = array_filter(
                $indexes,
                fn (Index $index): bool => count($index->columns) === 1
            );

            $compositeLeadingColumns = array_map(
                fn (Index $index): string => $index->columns[0],
                array_filter($indexes, fn (Index $index): bool => count($index->columns) > 1)
            );

            $uniqueSingleColumnColumns = array_map(
                fn (Index $index): string => $index->columns[0],
                array_filter($indexes, fn (Index $index): bool => count($index->columns) === 1 && $index->unique)
            );

            foreach ($singleColumnIndexes as $index) {
                $column = $index->columns[0];
                $isRedundantBecauseOfComposite = in_array($column, $compositeLeadingColumns, true);
                $isRedundantBecauseUniqueExists = in_array($column, $uniqueSingleColumnColumns, true) && ! $index->unique;

                if ($isRedundantBecauseOfComposite || $isRedundantBecauseUniqueExists) {
                    $reason = $isRedundantBecauseOfComposite
                        ? sprintf("a composite index leading with '%s' already covers it", $column)
                        : sprintf("a unique index on '%s' already covers it", $column);

                    $findings[] = $this->makeFinding(
                        table: $table->name,
                        message: sprintf("Single-column index on '%s' is redundant because %s. Consider removing the single-column index.", $column, $reason),
                        column: $column,
                    );
                }
            }
        }

        $context = $context->withFindings($findings);

        return $next($context);
    }
}
