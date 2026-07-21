<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Contracts\Rule;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\ValueObjects\Finding;

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
final class MismatchedForeignKeyRule implements Rule
{
    /** @var array<string, list<string>> */
    private const TYPE_FAMILIES = [
        'big'    => ['id', 'bigIncrements', 'foreignId', 'unsignedBigInteger'],
        'int'    => ['increments', 'unsignedInteger'],
        'small'  => ['smallIncrements', 'unsignedSmallInteger'],
        'medium' => ['mediumIncrements', 'unsignedMediumInteger'],
        'uuid'   => ['uuid', 'foreignUuid'],
        'ulid'   => ['ulid', 'foreignUlid'],
    ];

    public function check(array $tables): array
    {
        $findings = [];

        foreach ($tables as $table) {
            foreach ($table->foreignKeys() as $fk) {
                if ($fk->referencesTable === null) {
                    continue;
                }

                if (! isset($tables[$fk->referencesTable])) {
                    continue;
                }

                $referencedPkType = $tables[$fk->referencesTable]->primaryKeyColumnType();

                if ($referencedPkType === null) {
                    continue;
                }

                $fkColumnType = $table->columns()[$fk->column] ?? null;

                if ($fkColumnType === null) {
                    continue;
                }

                $fkFamily = $this->familyOf($fkColumnType);
                $pkFamily = $this->familyOf($referencedPkType);
                if ($fkFamily === null) {
                    continue;
                }

                if ($pkFamily === null) {
                    continue;
                }

                if ($fkFamily === $pkFamily) {
                    continue;
                }

                $findings[] = new Finding(
                    rule: 'mismatched_foreign_key',
                    table: $table->tableName,
                    message: sprintf("Foreign key '%s' has type '%s' which does not match primary key type '%s' on '%s'.", $fk->column, $fkColumnType, $referencedPkType, $fk->referencesTable),
                    column: $fk->column,
                    severity: Severity::Error,
                );
            }
        }

        return $findings;
    }

    private function familyOf(string $type): ?string
    {
        foreach (self::TYPE_FAMILIES as $family => $types) {
            if (in_array($type, $types, true)) {
                return $family;
            }
        }

        return null;
    }
}
