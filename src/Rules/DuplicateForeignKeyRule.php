<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\ValueObjects\Schema;

final class DuplicateForeignKeyRule extends Rule
{
    public function check(Schema $schema): array
    {
        $findings = [];

        foreach ($schema->tables() as $table) {
            $seen = [];

            foreach ($table->foreignKeys() as $fk) {
                if (isset($seen[$fk->column])) {
                    $findings[] = $this->finding(
                        table: $table,
                        message: sprintf("Duplicate foreign key on column '%s' - defined more than once. Remove the redundant constraint.", $fk->column),
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
