<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\Severity;
use Closure;

final readonly class DanglingForeignKeyRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            foreach ($table->danglingForeignKeys($context->schema) as $fk) {
                $findings[] = $this->makeFinding(
                    table: $table->name,
                    message: sprintf(
                        "References missing table <fg=white>%s</>",
                        $fk->referencesTable
                    ),
                    column: $fk->column,
                    severity: Severity::Error
                );
            }
        }

        return $next($context->withFindings($findings));
    }
}
