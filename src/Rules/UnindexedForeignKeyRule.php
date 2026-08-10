<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\Support\Config;
use Chr15k\SchemaAudit\ValueObjects\Finding;

final readonly class UnindexedForeignKeyRule extends Rule
{
    private const array AUTO_INDEXING_DRIVERS = ['mysql', 'mariadb'];

    public function __construct(private Config $config) {}

    protected function check(AuditContext $context): iterable
    {
        if (in_array($this->config->driver(), self::AUTO_INDEXING_DRIVERS, true)) {
            return;
        }

        foreach ($context->schema->tables() as $table) {
            foreach ($table->foreignKeys() as $fk) {
                if ($table->hasNoIndexFor($fk->columns)) {
                    yield $this->unindexedForeignKeyFinding($table, $fk);
                }
            }
        }
    }

    private function unindexedForeignKeyFinding(TableSchema $table, ForeignKey $fk): Finding
    {
        $columns = is_array($fk->columns)
            ? implode(', ', $fk->columns)
            : $fk->columns;

        $message = is_array($fk->columns)
            ? sprintf(
                '%s does not auto-index composite foreign key columns; add a composite index on %s (%s)',
                $this->config->driver(),
                $table->name,
                $columns,
            )
            : sprintf(
                '%s does not auto-index foreign key columns; add an index on %s.%s',
                $this->config->driver(),
                $table->name,
                $columns,
            );

        return $this->warning(
            table: $table,
            message: $message,
            columns: $columns,
            guard: $fk->guard(),
            location: $fk->location(),
        );
    }
}
