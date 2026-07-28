<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\PrimaryKey;
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
                if ($fk->referencesTable === null) {
                    continue; // no target table at all — DanglingForeignKeyRule's concern
                }

                $referencedTable = $context->schema->table($fk->referencesTable);

                if (! $referencedTable instanceof TableSchema) {
                    continue; // target table doesn't exist — DanglingForeignKeyRule's concern
                }

                $referencedColumn = $fk->referencesColumn ?? 'id';

                if ($this->hasUniqueConstraintOn($referencedTable, $referencedColumn)) {
                    continue;
                }

                $findings[] = $this->makeFinding(
                    table: $table->name,
                    message: sprintf(
                        "Foreign key on '%s' references '%s.%s', which has no unique key or primary key — MySQL/MariaDB will reject this constraint at migrate time.",
                        $fk->column,
                        $fk->referencesTable,
                        $referencedColumn,
                    ),
                    column: $fk->column,
                    severity: Severity::Error,
                );
            }
        }

        return $next($context->withFindings($findings));
    }

    private function hasUniqueConstraintOn(TableSchema $table, string $column): bool
    {
        $primaryKey = $table->primaryKey();

        if ($primaryKey instanceof PrimaryKey && $primaryKey->columns === [$column]) {
            return true;
        }

        foreach ($table->indexes() as $index) {
            if ($index->unique && $index->columns === [$column]) {
                return true;
            }
        }

        return false;
    }
}
