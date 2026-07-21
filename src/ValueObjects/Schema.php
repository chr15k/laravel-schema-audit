<?php

namespace Chr15k\SchemaAudit\ValueObjects;

use Chr15k\SchemaAudit\TableSchema;

final readonly class Schema
{
    /**
     * @param  array<string, TableSchema>  $tables
     */
    public function __construct(
        private array $tables,
    ) {}

    /**
     * @return list<TableSchema>
     */
    public function tables(): array
    {
        return array_values($this->tables);
    }

    public function hasTable(string $name): bool
    {
        return isset($this->tables[$name]);
    }

    public function table(string $name): ?TableSchema
    {
        return $this->tables[$name] ?? null;
    }
}
