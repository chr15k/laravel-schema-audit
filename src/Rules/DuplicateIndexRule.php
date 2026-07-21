<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\ValueObjects\Schema;

final readonly class DuplicateIndexRule extends Rule
{
    public function check(Schema $schema): array
    {
        $findings = [];

        foreach ($schema->tables() as $table) {
            $seen = [];

            foreach ($table->indexes() as $index) {
                if (isset($seen[$index->key()])) {
                    $findings[] = $this->makeFinding(
                        table: $table->name,
                        message: sprintf("Duplicate index on columns '%s' - declared more than once. Remove the redundant index.", implode(', ', $index->columns)),
                        column: implode(',', $index->columns),
                    );

                    continue;
                }

                $seen[$index->key()] = true;
            }
        }

        return $findings;
    }
}
