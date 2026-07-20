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
                        message: 'Duplicate foreign key on ('.$fk->column.') - defined more than once.',
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
