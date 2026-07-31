<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Closure;

final readonly class DuplicateIndexRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            foreach ($table->indexes()->duplicated() as $index) {
                $findings[] = $this->duplicateIndexFinding($table, $index);
            }
        }

        return $next($context->withFindings($findings));
    }

    private function duplicateIndexFinding(TableSchema $table, Index $index): Finding
    {
        $columns = implode(', ', $index->columns);

        return $this->makeFinding(
            table: $table->name,
            message: sprintf(
                'Duplicate index <fg=default>%s</> on <fg=default>%s</>',
                $index->name,
                $columns
            ),
            column: $columns,
            conditional: $table->isConditionallyModified()
        );
    }
}
