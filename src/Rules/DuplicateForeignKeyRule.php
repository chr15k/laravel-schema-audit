<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\ValueObjects\Schema;

final readonly class DuplicateForeignKeyRule extends Rule
{
    public function check(Schema $schema): array
    {
        $findings = [];

        foreach ($schema->tables() as $table) {
            $seen = [];

            foreach ($table->foreignKeys() as $fk) {
                if (isset($seen[$fk->column])) {
                    $findings[] = $this->makeFinding(
                        table: $table->name,
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
