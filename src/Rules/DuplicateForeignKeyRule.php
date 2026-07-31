<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Closure;

final readonly class DuplicateForeignKeyRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            foreach ($table->foreignKeys()->duplicated() as $fk) {
                $findings[] = $this->duplicateForeignKeyFinding($table, $fk);
            }
        }

        return $next($context->withFindings($findings));
    }

    private function duplicateForeignKeyFinding(TableSchema $table, ForeignKey $fk): Finding
    {
        return $this->makeFinding(
            table: $table->name,
            message: sprintf(
                'Duplicate foreign key on column <fg=default>%s</>',
                $fk->column,
            ),
            column: $fk->column,
            conditional: $table->isConditionallyModified()
        );
    }
}
