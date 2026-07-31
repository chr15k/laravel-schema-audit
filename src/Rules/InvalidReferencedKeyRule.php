<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Closure;

/**
 * Flags a foreign key whose referenced column has no unique constraint on
 * the parent table — MySQL/MariaDB reject this outright at migrate time
 * ("errno: 150 / 6125 - Missing unique key for constraint"), so this is a
 * real, migration-breaking bug rather than a style preference.
 */
final readonly class InvalidReferencedKeyRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            foreach ($table->foreignKeys() as $fk) {
                $referencedTable = $context->schema->table($fk->referencesTable);

                if (! $referencedTable instanceof TableSchema) {
                    continue;
                }

                if ($referencedTable->hasValidReferencedKey($fk->referencesColumn)) {
                    continue;
                }

                $findings[] = $this->makeFinding(
                    table: $table->name,
                    message: sprintf(
                        'Foreign key on <fg=default>%s</> references <fg=default>%s.%s</>, which has no unique key or primary key',
                        $fk->column,
                        $fk->referencesTable,
                        $fk->referencesColumn,
                    ),
                    column: $fk->column,
                    severity: Severity::Error,
                    conditional: $table->isConditionallyModified()
                );
            }
        }

        return $next($context->withFindings($findings));
    }
}
