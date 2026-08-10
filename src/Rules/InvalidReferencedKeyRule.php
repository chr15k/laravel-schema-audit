<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\TableSchema;

/**
 * Flags a foreign key whose referenced column has no unique constraint on
 * the parent table — MySQL/MariaDB reject this outright at migrate time
 * ("errno: 150 / 6125 - Missing unique key for constraint"), so this is a
 * real, migration-breaking bug rather than a style preference.
 */
final readonly class InvalidReferencedKeyRule extends Rule
{
    protected function check(AuditContext $context): iterable
    {
        foreach ($context->schema->tables() as $table) {
            foreach ($table->foreignKeys() as $fk) {
                $referencedTable = $context->schema->table($fk->referencesTable);

                if (! $referencedTable instanceof TableSchema) {
                    continue;
                }

                if ($referencedTable->hasValidReferencedKey($fk->referencesColumn)) {
                    continue;
                }

                $columns = is_array($fk->columns)
                    ? implode(', ', $fk->columns)
                    : $fk->columns;

                $referencedColumns = is_array($fk->referencesColumn)
                    ? implode(', ', $fk->referencesColumn)
                    : $fk->referencesColumn;

                yield $this->error(
                    table: $table,
                    message: sprintf(
                        'Foreign key on %s references %s (%s), which has no matching unique key or primary key',
                        $columns,
                        $fk->referencesTable,
                        $referencedColumns,
                    ),
                    columns: $fk->columns,
                    guard: $fk->guard,
                    location: $fk->location,
                );
            }
        }
    }
}
