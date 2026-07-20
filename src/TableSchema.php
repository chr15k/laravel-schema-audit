<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\ValueObjects\Index;

/**
 * Mutable, folded representation of a single table's schema, built up by
 * replaying every migration that touches it (Schema::create + Schema::table)
 * in filename/timestamp order.
 */
final class TableSchema
{
    /** @var array<string, string> column name => column type */
    private array $columns = [];

    /** @var list<Index> */
    private array $indexes = [];

    /** @var list<ForeignKey> */
    private array $foreignKeys = [];

    private bool $hasExplicitPrimaryKey = false;

    public function __construct(
        public readonly string $tableName,
    ) {}

    public function addColumn(string $name, string $type): void
    {
        $this->columns[$name] = $type;
    }

    public function dropColumn(string $name): void
    {
        unset($this->columns[$name]);

        // Dropping a column should also drop any index solely on that column.
        $this->indexes = array_values(array_filter(
            $this->indexes,
            fn (Index $index): bool => $index->columns !== [$name]
        ));
    }

    public function renameColumn(string $from, string $to): void
    {
        if (! array_key_exists($from, $this->columns)) {
            return; // renaming a column we never tracked — nothing to do
        }

        $this->columns[$to] = $this->columns[$from];
        unset($this->columns[$from]);

        // Any index referencing the old column name should follow the rename.
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

    /**
     * True if the table has a primary key — either via an auto-incrementing
     * id-style column (id(), increments(), bigIncrements(), etc., which
     * imply a primary key by Laravel convention) or an explicit
     * $table->primary(...) call.
     */
    public function hasPrimaryKey(): bool
    {
        $autoPrimaryTypes = ['id', 'increments', 'bigIncrements', 'smallIncrements', 'mediumIncrements'];

        foreach ($this->columns as $type) {
            if (in_array($type, $autoPrimaryTypes, true)) {
                return true;
            }
        }

        return $this->hasExplicitPrimaryKey;
    }

    public function addForeignKey(ForeignKey $fk): void
    {
        $this->foreignKeys[] = $fk;
    }

    /**
     * Drop a foreign key by explicit constraint name, or by column name
     * when no name was recorded (Laravel's default constraint naming
     * convention is table_column_foreign, but we don't reconstruct that
     * here — matching by column is the pragmatic fallback since a given
     * column typically has at most one FK).
     */
    public function dropForeignKey(string $nameOrColumn): void
    {
        $this->foreignKeys = array_values(array_filter(
            $this->foreignKeys,
            fn (ForeignKey $fk): bool => $fk->name !== $nameOrColumn && $fk->column !== $nameOrColumn
        ));
    }

    /** @return array<string, string> */
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

    /**
     * True if the given column is covered by *some* index — either as a
     * single-column index/unique, or as the leading column of a composite
     * index (leading-column semantics match how most DB engines use
     * composite indexes for single-column filters).
     */
    public function isIndexed(string $column): bool
    {
        foreach ($this->indexes as $index) {
            if (($index->columns[0] ?? null) === $column) {
                return true;
            }
        }

        foreach ($this->foreignKeys as $fk) {
            if ($fk->column === $column) {
                // Laravel's foreignId()->constrained() does NOT auto-index
                // on all DB drivers/versions, so we deliberately do not
                // treat "is a foreign key" as "is indexed" here — that gap
                // is exactly what rule UnindexedForeignKey checks for.
            }
        }

        return false;
    }

    /**
     * True if an index exists whose column list exactly matches the given
     * ordered column list (used for composite-mismatch checks in a later
     * phase — kept here now so the schema model doesn't need reshaping).
     *
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
            'table'   => $this->tableName,
            'columns' => $this->columns,
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
