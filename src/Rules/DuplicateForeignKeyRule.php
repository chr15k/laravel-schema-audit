<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
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
                $findings[] = $this->duplicateForeignKeyFinding($table->name, $fk);
            }
        }

        return $next($context->withFindings($findings));
    }

    private function duplicateForeignKeyFinding(string $table, ForeignKey $fk): Finding
    {
        return $this->makeFinding(
            table: $table,
            message: sprintf("Duplicate foreign key on column '%s' - defined more than once. Remove the redundant constraint.", $fk->column),
            column: $fk->column,
        );
    }
}
