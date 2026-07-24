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
            foreach ($table->foreignKeys() as $fk) {
                if ($fk->referencesTable === null) {
                    continue;
                }

                if ($context->schema->hasTable($fk->referencesTable)) {
                    continue;
                }

                $findings[] = $this->makeFinding(
                    table: $table->name,
                    message: sprintf("Foreign key '%s' references missing table '%s'. Ensure the referenced table exists or update the foreign key.", $fk->column, $fk->referencesTable),
                    column: $fk->column,
                    severity: Severity::Error
                );
            }
        }

        $context = $context->withFindings($findings);

        return $next($context);
    }
}
