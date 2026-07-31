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
            if ($table->isConditionallyModified()) {
                // Skip tables modified by conditional migrations to avoid false-positives.
                continue;
            }

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
            message: sprintf(
                'Duplicate foreign key on column <fg=default>%s</>',
                $fk->column,
            ),
            column: $fk->column,
        );
    }
}
