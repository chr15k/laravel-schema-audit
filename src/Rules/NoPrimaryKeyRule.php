<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Contracts\Rule;

/**
 * Flags a table with no identifiable primary key — neither an
 * auto-incrementing id-style column nor an explicit $table->primary(...)
 * call was found anywhere in its migration history. Rare, but a real and
 * easy-to-miss mistake (e.g. a pivot table someone forgot to add a
 * composite primary key to).
 */
final class NoPrimaryKeyRule implements Rule
{
    public function check(array $tables): array
    {
        $findings = [];

        foreach ($tables as $table) {
            if (! $table->hasPrimaryKey()) {
                $findings[] = new Finding(
                    rule: 'no_primary_key',
                    table: $table->tableName,
                    message: 'No primary key found — no id()/increments()-style column and no explicit primary() call.',
                );
            }
        }

        return $findings;
    }
}
