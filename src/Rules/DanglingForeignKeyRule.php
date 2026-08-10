<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;

final readonly class DanglingForeignKeyRule extends Rule
{
    protected function check(AuditContext $context): iterable
    {
        foreach ($context->schema->tables() as $table) {
            foreach ($table->danglingForeignKeys($context->schema) as $fk) {
                yield $this->error(
                    table: $table,
                    message: sprintf(
                        'References missing table %s',
                        $fk->referencesTable
                    ),
                    columns: $fk->columns,
                    guard: $fk->guard,
                    location: $fk->location
                );
            }
        }
    }
}
