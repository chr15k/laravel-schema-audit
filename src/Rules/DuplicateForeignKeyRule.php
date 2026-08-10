<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\DuplicateForeignKeyGroup;
use Chr15k\SchemaAudit\ValueObjects\Finding;

final readonly class DuplicateForeignKeyRule extends Rule
{
    protected function check(AuditContext $context): iterable
    {
        foreach ($context->schema->tables() as $table) {
            foreach ($table->foreignKeys()->duplicateGroups() as $group) {
                yield $this->duplicateForeignKeyFinding($table, $group);
            }
        }
    }

    private function duplicateForeignKeyFinding(
        TableSchema $table,
        DuplicateForeignKeyGroup $group
    ): Finding {
        $fk = $group->primary();
        $columns = $group->columns();

        return $this->warning(
            table: $table,
            message: sprintf(
                'Duplicate foreign key on column %s',
                implode(', ', $columns)
            ),
            columns: $columns,
            guard: $fk->guard(),
            location: $fk->location(),
            related: $group->related()
        );
    }
}
