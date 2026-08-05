<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

use Chr15k\SchemaAudit\Contracts\SchemaReference;
use Chr15k\SchemaAudit\Enums\ColumnFamily;
use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SourceLocation;
use Chr15k\SchemaAudit\Schema\Collections\ColumnCollection;
use Chr15k\SchemaAudit\Schema\Collections\ForeignKeyCollection;
use Chr15k\SchemaAudit\Schema\Collections\IndexCollection;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, list<array<string, bool|list<string>|string|null>>|string>
 */
final class TableSchema implements Arrayable, JsonSerializable, SchemaReference
{
    private ?SourceLocation $location = null;

    private ?SchemaGuard $guard = null;

    private ?ValueObjects\PrimaryKey $primaryKey = null;

    /**
     * @param  IndexCollection<int, ValueObjects\Index>  $indexes
     * @param  ForeignKeyCollection<int, ValueObjects\ForeignKey>  $foreignKeys
     * @param  ColumnCollection<string, Column>  $columns
     */
    public function __construct(
        public readonly string $name,
        private IndexCollection $indexes,
        private ForeignKeyCollection $foreignKeys,
        private readonly ColumnCollection $columns
    ) {}

    public static function make(
        string $name,
        ?SourceLocation $location = null,
        ?SchemaGuard $guard = null
    ): self {
        $instance = app(self::class, ['name' => $name]);

        if ($location instanceof SourceLocation) {
            $instance->setLocation($location);
        }

        if ($guard instanceof SchemaGuard) {
            $instance->setGuard($guard);
        }

        return $instance;
    }

    public function setGuard(?SchemaGuard $guard = null): void
    {
        $this->guard = $guard;
    }

    public function setLocation(?SourceLocation $location = null): void
    {
        $this->location = $location;
    }

    public function location(): ?SourceLocation
    {
        return $this->location;
    }

    public function guard(): ?SchemaGuard
    {
        return $this->guard;
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

    public function column(string $name): ?Column
    {
        return $this->columns->get($name);
    }

    public function addColumn(Column $column): void
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
     * @return ColumnCollection<Column>
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
        return $this->foreignKeys->reject(
            fn (ValueObjects\ForeignKey $fk): bool => $schema->hasTable($fk->referencesTable)
        );
    }

    public function hasMatchingForeignKeyType(
        ValueObjects\ForeignKey $foreignKey,
        self $referencedTable,
    ): bool {
        // Composite foreign keys are intentionally skipped here.
        // The schema model keeps columns in a string-keyed collection, so
        // single-column family comparison is not defined for array-valued
        // FK column lists. Conservative behavior avoids false positives.
        if (is_array($foreignKey->columns) || is_array($foreignKey->referencesColumn)) {
            return true;
        }

        $column = $this->column($foreignKey->columns);

        if (! $column instanceof Column) {
            return true;
        }

        $referencedColumn = $referencedTable->column($foreignKey->referencesColumn);

        if (! $referencedColumn instanceof Column) {
            return true;
        }

        $columnFamily = $column->method->family();
        $referencedFamily = $referencedColumn->method->family();

        // Skip mismatch detection when either column type is unrecognized.
        if ($columnFamily === ColumnFamily::Other || $referencedFamily === ColumnFamily::Other) {
            return true;
        }

        return $columnFamily === $referencedFamily;
    }

    /**
     * @return array{
     *     table: string,
     *     primary_key: ?ValueObjects\PrimaryKey,
     *     columns: ColumnCollection<string, Column>,
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
     *     columns: ColumnCollection<string, Column>,
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
