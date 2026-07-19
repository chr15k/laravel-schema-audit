<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Contracts\Rule;
use Chr15k\SchemaAudit\Data\Finding;

/**
 * Flags foreign key columns with no covering index — driver-aware, since
 * MySQL/InnoDB auto-creates an index when a FK constraint is added, but
 * PostgreSQL and SQLite do not. Flagging every bare foreignId() on a
 * MySQL codebase would be almost entirely false positives, which is why
 * this needs the driver rather than assuming the worst case everywhere.
 */
final readonly class UnindexedForeignKeyRule implements Rule
{
    private const AUTO_INDEXING_DRIVERS = ['mysql', 'mariadb'];

    public function __construct(private string $driver) {}

    public function check(array $tables): array
    {
        if (in_array($this->driver, self::AUTO_INDEXING_DRIVERS, true)) {
            return [];
        }

        $findings = [];

        foreach ($tables as $table) {
            foreach ($table->foreignKeys() as $fk) {
                if (! $table->isIndexed($fk->column)) {
                    $findings[] = new Finding(
                        rule: 'unindexed_foreign_key',
                        table: $table->tableName,
                        message: sprintf("Foreign key '%s' has no index. %s does not auto-index foreign key columns.", $fk->column, $this->driver),
                        column: $fk->column,
                    );
                }
            }
        }

        return $findings;
    }
}
