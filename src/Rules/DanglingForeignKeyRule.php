<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\ValueObjects\Schema;

final readonly class DanglingForeignKeyRule extends Rule
{
    public function check(Schema $schema): array
    {
        $findings = [];

        foreach ($schema->tables() as $table) {
            foreach ($table->foreignKeys() as $fk) {
                if ($fk->referencesTable === null || $schema->hasTable($fk->referencesTable)) {
                    continue;
                }

                $findings[] = $this->makeFinding(
                    table: $table->name,
                    column: $fk->column,
                    message: sprintf("Foreign key '%s' references missing table '%s'. Ensure the referenced table exists or update the foreign key.", $fk->column, $fk->referencesTable),
                    severity: Severity::Error
                );
        }

        return $findings;
    }
}
