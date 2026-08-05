<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\DuplicateForeignKeyGroup;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Closure;

final readonly class DuplicateForeignKeyRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            foreach ($table->foreignKeys()->duplicateGroups() as $duplicates) {
                $findings[] = $this->duplicateForeignKeyFinding($table, $duplicates);
            }
        }

        return $next($context->withFindings($findings));
    }

    private function duplicateForeignKeyFinding(
        TableSchema $table,
        DuplicateForeignKeyGroup $group
    ): Finding {
        $fk = $group->primary();
        $columns = $group->columns();

        return $this->makeFinding(
            table: $table->name,
            message: sprintf(
                'Duplicate foreign key on column <fg=default>%s</>',
                implode(', ', $columns)
            ),
            column: $columns,
            guard: $fk->guard(),
            location: $fk->location(),
            related: $group->related()
        );
    }
}
