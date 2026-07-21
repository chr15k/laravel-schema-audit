<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\ValueObjects\Index;
use Chr15k\SchemaAudit\ValueObjects\Schema;

final readonly class RedundantSingleColumnIndexRule extends Rule
{
    public function check(Schema $schema): array
    {
        $findings = [];

        foreach ($schema->tables() as $table) {
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
                    $findings[] = $this->makeFinding(
                        table: $table->name,
                        message: sprintf("Single-column index on '%s' is redundant because a composite index leading with '%s' already covers it. Consider removing the single-column index.", $column, $column),
                        column: $column,
                    );
                }
            }
        }

        return $findings;
    }
}
