<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Rules;

use Chr15k\SchemaAudit\Schema\Contracts\Rule;

/**
 * Flags a foreign key whose referenced table doesn't exist in the folded
 * schema at all — a typo, or a table that was renamed/dropped without
 * updating the reference. Only fires when the referenced table name was
 * actually resolved (foreignId()->constrained() without an explicit
 * table name is skipped — inferring the conventional table name from the
 * column name is a guess we don't want to make findings out of).
 */
final class DanglingForeignKeyRule implements Rule
{
    public function check(array $tables): array
    {
        $findings = [];

        foreach ($tables as $table) {
            foreach ($table->foreignKeys() as $fk) {
                if ($fk->referencesTable === null) {
                    continue;
                }

                if (! array_key_exists($fk->referencesTable, $tables)) {
                    $findings[] = new Finding(
                        rule: 'dangling_foreign_key',
                        table: $table->tableName,
                        message: sprintf("Foreign key '%s' references table '%s', which doesn't exist in the folded schema.", $fk->column, $fk->referencesTable),
                        column: $fk->column,
                    );
                }
            }
        }

        return $findings;
    }
}
