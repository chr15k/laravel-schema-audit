<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Rules;

use Chr15k\SchemaAudit\Schema\Data\Index;

/**
 * Flags a single-column index that is already covered by a composite
 * index whose leading column is the same — the single-column index adds
 * nothing a query planner can't already get from the composite's prefix
 * (standard B-tree leading-column semantics), so it's pure write overhead.
 */
final class RedundantSingleColumnIndexRule implements Rule
{
    public function check(array $tables): array
    {
        $findings = [];

        foreach ($tables as $table) {
            $singleColumnIndexes = array_filter(
                $table->indexes(),
                fn (Index $index): bool => count($index->columns) === 1
            );

            $compositeLeadingColumns = array_map(
                fn (Index $index): string => $index->columns[0],
                array_filter($table->indexes(), fn (Index $index): bool => count($index->columns) > 1)
            );

            foreach ($singleColumnIndexes as $index) {
                $column = $index->columns[0];

                if (in_array($column, $compositeLeadingColumns, true)) {
                    $findings[] = new Finding(
                        rule: 'redundant_single_column_index',
                        table: $table->tableName,
                        message: sprintf("Single-column index on '%s' is redundant — already covered by a composite index leading with '%s'.", $column, $column),
                        column: $column,
                    );
                }
            }
        }

        return $findings;
    }
}
