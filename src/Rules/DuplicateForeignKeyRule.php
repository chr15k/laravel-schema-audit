<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Contracts\Rule;
use Chr15k\SchemaAudit\ValueObjects\Finding;

final class DuplicateForeignKeyRule implements Rule
{
    public function check(array $tables): array
    {
        $findings = [];

        foreach ($tables as $table) {
            $seen = [];

            foreach ($table->foreignKeys() as $fk) {
                if (isset($seen[$fk->column])) {
                    $findings[] = new Finding(
                        rule: 'duplicate_foreign_key',
                        table: $table->tableName,
                        message: sprintf("Duplicate foreign key on column '%s' — defined more than once. Remove the redundant constraint.", $fk->column),
                        column: $fk->column,
                    );

                    continue;
                }

                $seen[$fk->column] = true;
            }
        }

        return $findings;
    }
}
