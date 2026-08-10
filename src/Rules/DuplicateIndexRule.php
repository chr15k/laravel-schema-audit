<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\DuplicateIndexGroup;
use Chr15k\SchemaAudit\ValueObjects\Finding;

final readonly class DuplicateIndexRule extends Rule
{
    protected function check(AuditContext $context): iterable
    {
        foreach ($context->schema->tables() as $table) {
            foreach ($table->indexes()->duplicateGroups() as $groups) {
                yield $this->duplicateIndexFinding($table, $groups);
            }
        }
    }

    private function duplicateIndexFinding(
        TableSchema $table,
        DuplicateIndexGroup $group
    ): Finding {
        $index = $group->primary();
        $columns = $group->columns();

        return $this->warning(
            table: $table,
            message: sprintf(
                'Duplicate index %s on %s',
                $index->name,
                implode(', ', $columns)
            ),
            columns: $columns,
            guard: $index->guard(),
            location: $index->location(),
            related: $group->related()
        );
    }
}
