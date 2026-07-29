<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

use Chr15k\SchemaAudit\Enums\ColumnFamily;
use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Schema\Collections\ColumnCollection;
use Chr15k\SchemaAudit\Schema\Collections\ForeignKeyCollection;
use Chr15k\SchemaAudit\Schema\Collections\IndexCollection;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, list<array<string, bool|list<string>|string|null>>|string>
 */
final class TableSchema implements Arrayable, JsonSerializable
{
    private ?ValueObjects\PrimaryKey $primaryKey = null;

    /**
     * @param  IndexCollection<int, ValueObjects\Index>  $indexes
     * @param  ForeignKeyCollection<int, ValueObjects\ForeignKey>  $foreignKeys
     * @param  ColumnCollection<string, ValueObjects\Column>  $columns
     */
    public function __construct(
        public readonly string $name,
        private IndexCollection $indexes,
        private ForeignKeyCollection $foreignKeys,
        private readonly ColumnCollection $columns
    ) {}

    public static function make(string $name): self
    {
        return app(self::class, ['name' => $name]);
    }

    public function setPrimaryKey(ValueObjects\PrimaryKey $primaryKey): void
    {
        $this->primaryKey = $primaryKey;
    }

    public function primaryKey(): ?ValueObjects\PrimaryKey
    {
        return $this->primaryKey;
    }

    public function hasPrimaryKey(): bool
    {
        return $this->primaryKey instanceof ValueObjects\PrimaryKey;
    }

    public function addColumn(ValueObjects\Column $column): void
    {
        $this->columns->put($column->name, $column);
    }

    public function dropColumn(string $name): void
    {
        $this->columns->forget($name);

        $this->indexes = $this->indexes->withoutColumn($name);
    }

    public function hasColumn(string $name): bool
    {
        return $this->columns->has($name);
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->columns->rename($from, $to);

        $this->indexes = $this->indexes->renameColumn($from, $to);

        $this->foreignKeys = $this->foreignKeys->renameColumn($from, $to);
    }

    public function addIndex(ValueObjects\Index $index): void
    {
        $this->indexes->push($index);
    }

    public function removeIndex(string $indexName): void
    {
        $this->indexes = $this->indexes->withoutName($indexName);
    }

    public function primaryKeyColumnMethod(): ?ColumnMethod
    {
        return $this->columns->primaryKeyColumnMethod();
    }

    public function addForeignKey(ValueObjects\ForeignKey $fk): void
    {
        $this->foreignKeys->push($fk);
    }

    public function removeForeignKey(string $name): void
    {
        $this->foreignKeys = $this->foreignKeys->withoutName($name);
    }

    public function renameReferencedTable(string $from, string $to): void
    {
        $this->foreignKeys = $this->foreignKeys->renameReferencedTable($from, $to);
    }

    /**
     * @return ColumnCollection<ValueObjects\Column>
     */
    public function columns(): ColumnCollection
    {
        return $this->columns;
    }

    /**
     * @return IndexCollection<ValueObjects\Index>
     */
    public function indexes(): IndexCollection
    {
        return $this->indexes;
    }

    /**
     * @return ForeignKeyCollection<ValueObjects\ForeignKey>
     */
    public function foreignKeys(): ForeignKeyCollection
    {
        return $this->foreignKeys;
    }

    public function indexesColumn(string $column): bool
    {
        // Laravel's foreignId()->constrained() does NOT auto-index
        // on all DB drivers/versions, so we deliberately do not
        // treat "is a foreign key" as "is indexed" here - that gap
        // is exactly what rule UnindexedForeignKey checks for.
        return $this->indexes->indexesColumn($column);
    }

    /**
     * @param  list<string>  $columns
     */
    public function hasExactColumns(array $columns): bool
    {
        return $this->indexes->hasExactColumns($columns);
    }

    public function hasValidReferencedKey(string $column): bool
    {
        $primaryKey = $this->primaryKey;

        if ($primaryKey instanceof ValueObjects\PrimaryKey && $primaryKey->columns === [$column]) {
            return true;
        }

        return $this->indexes->contains(
            fn (ValueObjects\Index $index): bool => $index->unique && $index->columns === [$column]
        );
    }

    public function danglingForeignKeys(Schema $schema): ForeignKeyCollection
    {
        return $this->foreignKeys->filter(
            fn (ValueObjects\ForeignKey $fk): bool => $fk->referencesTable !== null
                && ! $schema->hasTable($fk->referencesTable)
        );
    }

    public function hasMatchingForeignKeyType(
        ValueObjects\ForeignKey $foreignKey,
        self $referencedTable,
    ): bool {
        /** @var ?ValueObjects\Column $column */
        $column = $this->columns->get($foreignKey->column);

        if ($column === null) {
            return true;
        }

        $referencedPkType = $referencedTable->primaryKeyColumnMethod();

        if (! $referencedPkType instanceof ColumnMethod) {
            return true;
        }

        $columnFamily = $column->method->family();
        $primaryKeyFamily = $referencedPkType->family();

        if (! $columnFamily instanceof ColumnFamily) {
            return true;
        }

        if (! $primaryKeyFamily instanceof ColumnFamily) {
            return true;
        }

        return $columnFamily === $primaryKeyFamily;
    }

    /**
     * @return array{
     *     table: string,
     *     primary_key: ?ValueObjects\PrimaryKey,
     *     columns: ColumnCollection<string, ValueObjects\Column>,
     *     indexes: IndexCollection<int, ValueObjects\Index>,
     *     foreign_keys: ForeignKeyCollection<int, ValueObjects\ForeignKey>,
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{
     *     table: string,
     *     primary_key: ?ValueObjects\PrimaryKey,
     *     columns: ColumnCollection<string, ValueObjects\Column>,
     *     indexes: IndexCollection<int, ValueObjects\Index>,
     *     foreign_keys: ForeignKeyCollection<int, ValueObjects\ForeignKey>,
     * }
     */
    public function toArray(): array
    {
        return [
            'table'        => $this->name,
            'primary_key'  => $this->primaryKey,
            'columns'      => $this->columns->values(),
            'indexes'      => $this->indexes,
            'foreign_keys' => $this->foreignKeys,
        ];
    }
}
