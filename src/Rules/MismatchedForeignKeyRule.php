<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\ValueObjects\Schema;

/**
 * Flags a foreign key whose column type doesn't match the type family of
 * the referenced table's primary key — e.g. a foreignId() (unsigned big
 * integer) pointing at a table whose PK is a plain increments() (unsigned
 * integer). MySQL frequently rejects this outright at migrate time;
 * SQLite/loosely-enforced setups can let it exist silently.
 *
 * Deliberately conservative: only fires when BOTH sides resolve with
 * confidence — the referenced table exists (DanglingForeignKeyRule
 * covers the case where it doesn't), and its primary key type is
 * resolvable via the auto-increment convention. A table using an
 * explicit $table->primary(...) call is silently skipped rather than
 * guessed at, since a wrong guess here produces a false mismatch, which
 * is worse than a missed one.
 */
final readonly class MismatchedForeignKeyRule extends Rule
{
    public function check(Schema $schema): array
    {
        $findings = [];

        foreach ($schema->tables() as $table) {
            foreach ($table->foreignKeys() as $fk) {
                if ($fk->referencesTable === null) {
                    continue;
                }

                if (! $schema->hasTable($fk->referencesTable)) {
                    continue;
                }

                $referencedPkType = $schema->table($fk->referencesTable)?->primaryKeyColumnType();

                if ($referencedPkType === null) {
                    continue;
                }

                $fkColumnType = $table->columns()[$fk->column] ?? null;

                if ($fkColumnType === null) {
                    continue;
                }

                $fkFamily = $fkColumnType->family();
                $pkFamily = $referencedPkType->family();

                if ($fkFamily === null) {
                    continue;
                }

                if ($pkFamily === null) {
                    continue;
                }

                if ($fkFamily === $pkFamily) {
                    continue;
                }

                $findings[] = $this->makeFinding(
                    table: $table->name,
                    message: sprintf(
                        "Foreign key on '%s' (%s) does not match key type '%s' on '%s'.",
                        $fk->column,
                        $fkColumnType->toType() ?? $fkColumnType,
                        $referencedPkType->toType() ?? $referencedPkType,
                        $fk->referencesTable
                    ),
                    column: $fk->column,
                    severity: Severity::Error,
                );
            }
        }

        return $findings;
    }
}
