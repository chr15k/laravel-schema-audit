<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Contracts\Rule;
use Chr15k\SchemaAudit\ValueObjects\Finding;

/**
 * Flags two or more indexes on the same table covering the exact same
 * column list (order-sensitive, since a composite index's column order
 * matters for query planning). Pure dead weight — every write pays the
 * cost of maintaining both, for zero additional read benefit.
 */
final class DuplicateIndexRule implements Rule
{
    public function check(array $tables): array
    {
        $findings = [];

        foreach ($tables as $table) {
            $seen = [];

            foreach ($table->indexes() as $index) {
                $key = implode(',', $index->columns).'|'.($index->unique ? 'unique' : 'plain');

                if (isset($seen[$key])) {
                    $findings[] = new Finding(
                        rule: 'duplicate_index',
                        table: $table->tableName,
                        message: sprintf("Duplicate index on columns '%s' — declared more than once. Remove the redundant index.", implode(', ', $index->columns)),
                        column: implode(',', $index->columns),
                    );

                    continue;
                }

                $seen[$key] = true;
            }
        }

        return $findings;
    }
}
