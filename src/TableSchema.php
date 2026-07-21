<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\ValueObjects\Index;
use Illuminate\Contracts\Support\Arrayable;

final class TableSchema implements Arrayable
{
    /** @var array<string, ColumnMethod> */
    private array $columns = [];

    /** @var list<Index> */
    private array $indexes = [];

    /** @var list<ForeignKey> */
    private array $foreignKeys = [];

    private bool $hasExplicitPrimaryKey = false;

    public function __construct(
        public readonly string $name,
    ) {}

    public function addColumn(string $name, ColumnMethod $type): void
    {
        $this->columns[$name] = $type;
    }

    public function dropColumn(string $name): void
    {
        unset($this->columns[$name]);

        $this->indexes = array_values(array_filter(
            $this->indexes,
            fn (Index $index): bool => $index->columns !== [$name]
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
            fn (Index $index): Index => new Index(
                name: $index->name,
                columns: array_map(fn (string $c): string => $c === $from ? $to : $c, $index->columns),
                unique: $index->unique,
            ),
            $this->indexes
        );
    }

    public function addIndex(Index $index): void
    {
        $this->indexes[] = $index;
    }

    public function dropIndex(string $indexName): void
    {
        $this->indexes = array_values(array_filter(
            $this->indexes,
            fn (Index $index): bool => $index->name !== $indexName
        ));
    }

    public function markPrimaryKey(): void
    {
        $this->hasExplicitPrimaryKey = true;
    }

    public function primaryKeyColumnType(): ?ColumnMethod
    {
        foreach ($this->columns as $type) {
            if ($type->impliesAutoIncrementingPrimaryKey() === true) {
                return $type;
            }
        }

        return null;
    }

    public function hasPrimaryKey(): bool
    {
        if ($this->primaryKeyColumnType() !== null) {
            return true;
        }

        return $this->hasExplicitPrimaryKey;
    }

    public function addForeignKey(ForeignKey $fk): void
    {
        $this->foreignKeys[] = $fk;
    }

    public function dropForeignKey(string $nameOrColumn): void
    {
        $this->foreignKeys = array_values(array_filter(
            $this->foreignKeys,
            fn (ForeignKey $fk): bool => $fk->name !== $nameOrColumn && $fk->column !== $nameOrColumn
        ));
    }

    /** @return array<string, ColumnMethod> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return list<Index> */
    public function indexes(): array
    {
        return $this->indexes;
    }

    /** @return list<ForeignKey> */
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
     * @return array{table: string, columns: array<string,string>, indexes: list<array{name:?string,columns:list<string>,unique:bool}>, foreign_keys: list<array{column:string,references_table:?string}>}
     */
    public function toArray(): array
    {
        return [
            'table'   => $this->name,
            'columns' => array_map(
                fn (string $name, ColumnMethod $method): array => [
                    'name'   => $name,
                    'method' => $method,
                ],
                $this->columns,
            ),
            'indexes' => array_map(
                fn (Index $i): array => [
                    'name'    => $i->name,
                    'columns' => $i->columns,
                    'unique'  => $i->unique,
                ],
                $this->indexes
            ),
            'foreign_keys' => array_map(
                fn (ForeignKey $fk): array => [
                    'column'           => $fk->column,
                    'references_table' => $fk->referencesTable,
                ],
                $this->foreignKeys
            ),
        ];
    }
}
