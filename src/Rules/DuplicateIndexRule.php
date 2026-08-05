<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\DuplicateIndexGroup;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Closure;

final readonly class DuplicateIndexRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            foreach ($table->indexes()->duplicateGroups() as $duplicates) {
                $findings[] = $this->duplicateIndexFinding($table, $duplicates);
            }
        }

        return $next($context->withFindings($findings));
    }

    private function duplicateIndexFinding(
        TableSchema $table,
        DuplicateIndexGroup $group
    ): Finding {
        $index = $group->primary();
        $columns = $group->columns();

        return $this->makeFinding(
            table: $table->name,
            message: sprintf(
                'Duplicate index <fg=default>%s</> on <fg=default>%s</>',
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
