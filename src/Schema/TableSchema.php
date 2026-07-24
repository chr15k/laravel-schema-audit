<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, list<array<string, bool|list<string>|string|null>>|string>
 */
final class TableSchema implements Arrayable, JsonSerializable
{
    /** @var array<string, ValueObjects\Column> */
    private array $columns = [];

    /** @var list<ValueObjects\Index> */
    private array $indexes = [];

    /** @var list<ValueObjects\ForeignKey> */
    private array $foreignKeys = [];

    private bool $hasExplicitPrimaryKey = false;

    public function __construct(
        public readonly string $name
    ) {}

    public function addColumn(ValueObjects\Column $column): void
    {
        $this->columns[$column->name] = $column;
    }

    public function dropColumn(string $name): void
    {
        unset($this->columns[$name]);

        $this->indexes = array_values(array_filter(
            $this->indexes,
            fn (ValueObjects\Index $index): bool => $index->columns !== [$name]
        ));
    }

    public function renameColumn(string $from, string $to): void
    {
        if (! array_key_exists($from, $this->columns)) {
            return;
        }

        $this->columns[$to] = $this->columns[$from];
        unset($this->columns[$from]);

        $this->indexes = array_map(
            fn (ValueObjects\Index $index): ValueObjects\Index => new ValueObjects\Index(
                name: $index->name,
                columns: array_map(fn (string $column): string => $column === $from ? $to : $column, $index->columns),
                unique: $index->unique,
            ),
            $this->indexes
        );
    }

    public function addIndex(ValueObjects\Index $index): void
    {
        $this->indexes[] = $index;
    }

    public function dropIndex(string $indexName): void
    {
        $this->indexes = array_values(array_filter(
            $this->indexes,
            fn (ValueObjects\Index $index): bool => $index->name !== $indexName
        ));
    }

    public function markPrimaryKey(): void
    {
        $this->hasExplicitPrimaryKey = true;
    }

    public function primaryKeyColumnType(): ?ColumnMethod
    {
        foreach ($this->columns as $column) {
            if ($column->method->impliesAutoIncrementingPrimaryKey()) {
                return $column->method;
            }
        }

        return null;
    }

    public function hasPrimaryKey(): bool
    {
        if ($this->primaryKeyColumnType() instanceof ColumnMethod) {
            return true;
        }

        return $this->hasExplicitPrimaryKey;
    }

    public function addForeignKey(ValueObjects\ForeignKey $fk): void
    {
        $this->foreignKeys[] = $fk;
    }

    public function dropForeignKey(string $index): void
    {
        $this->foreignKeys = array_values(array_filter(
            $this->foreignKeys,
            fn (ValueObjects\ForeignKey $fk): bool => $fk->name !== $index
        ));
    }

    /** @return array<string,ValueObjects\Column> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return list<ValueObjects\Index> */
    public function indexes(): array
    {
        return $this->indexes;
    }

    /** @return list<ValueObjects\ForeignKey> */
    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function hasColumn(string $name): bool
    {
        return array_key_exists($name, $this->columns);
    }

    public function isIndexed(string $column): bool
    {
        foreach ($this->indexes as $index) {
            if (($index->columns[0] ?? null) === $column) {
                return true;
            }
        }

        // Laravel's foreignId()->constrained() does NOT auto-index
        // on all DB drivers/versions, so we deliberately do not
        // treat "is a foreign key" as "is indexed" here - that gap
        // is exactly what rule UnindexedForeignKey checks for.

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    public function hasExactCompositeIndex(array $columns): bool
    {
        foreach ($this->indexes as $index) {
            if ($index->columns === $columns) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     table: string,
     *     columns: list<ValueObjects\Column>,
     *     indexes: list<ValueObjects\Index>,
     *     foreign_keys: list<ValueObjects\ForeignKey>,
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{
     *     table: string,
     *     columns: list<ValueObjects\Column>,
     *     indexes: list<ValueObjects\Index>,
     *     foreign_keys: list<ValueObjects\ForeignKey>,
     * }
     */
    public function toArray(): array
    {
        return [
            'table'        => $this->name,
            'columns'      => array_values($this->columns),
            'indexes'      => $this->indexes,
            'foreign_keys' => $this->foreignKeys,
        ];
    }
}
