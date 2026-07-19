<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Contracts\Rule;

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
                        message: 'Duplicate index on ('.implode(', ', $index->columns).') — defined more than once.',
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
