<?php

namespace Chr15k\SchemaAudit\Schema;

use Chr15k\SchemaAudit\Exceptions\JsonEncodingException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonException;
use JsonSerializable;

/**
 * @implements Arrayable<int, array>
 */
final readonly class Schema implements Arrayable, Jsonable, JsonSerializable
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

    public function tableCount(): int
    {
        return count($this->tables);
    }

    /**
     * @return list<array{name: string, schema: TableSchema}>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson($options = 0)
    {
        try {
            return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw JsonEncodingException::forSchema($this, $jsonException);
        }
    }

    public function toPrettyJson(int $options = 0): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | $options);
    }

    /**
     * @return list<array{name: string, schema: TableSchema}>
     */
    public function toArray(): array
    {
        return array_map(
            fn (string $name, TableSchema $table): array => [
                'name'   => $name,
                'schema' => $table,
            ],
            array_keys($this->tables),
            $this->tables,
        );
    }
}
