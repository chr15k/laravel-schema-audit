<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\ValueObjects\Schema;

final readonly class UnindexedForeignKeyRule extends Rule
{
    private const array AUTO_INDEXING_DRIVERS = ['mysql', 'mariadb'];

    public function __construct(private string $driver) {}

    public function check(Schema $schema): array
    {
        if (in_array($this->driver, self::AUTO_INDEXING_DRIVERS, true)) {
            return [];
        }

        $findings = [];

        foreach ($schema->tables() as $table) {
            foreach ($table->foreignKeys() as $fk) {
                if (! $table->isIndexed($fk->column)) {
                    $findings[] = $this->makeFinding(
                        table: $table->name,
                        message: sprintf("Foreign key '%s' has no covering index. %s does not auto-index foreign key columns; add an index to improve lookup and join performance.", $fk->column, $this->driver),
                        column: $fk->column,
                    );
                }
            }
        }

        return $findings;
    }
}
