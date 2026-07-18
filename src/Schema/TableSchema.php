<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

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

    public function addForeignKey(ForeignKey $fk): void
    {
        $this->foreignKeys[] = $fk;
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
