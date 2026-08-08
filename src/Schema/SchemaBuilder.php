<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

use Chr15k\SchemaAudit\Contracts\SchemaSource;
use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Enums\SchemaOperationType;
use Chr15k\SchemaAudit\Enums\StructuralMethod;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnCall;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnChain;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SchemaOperation;
use Chr15k\SchemaAudit\Schema\Resolvers\ColumnResolver;
use Chr15k\SchemaAudit\Schema\Resolvers\ForeignKeyResolver;
use Chr15k\SchemaAudit\Schema\Resolvers\IndexResolver;
use Chr15k\SchemaAudit\Schema\Resolvers\PrimaryKeyResolver;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Closure;

final readonly class SchemaBuilder
{
    public function __construct(
        private IndexResolver $indexes,
        private ForeignKeyResolver $foreignKeys,
        private ColumnResolver $columns,
        private PrimaryKeyResolver $primaryKeys,
        private LaravelConventions $conventions
    ) {}

    public function build(
        SchemaSource $source,
        ?Closure $progress = null
    ): Schema {
        $tables = [];

        foreach ($source->operations() as $operation) {
            $this->applyOperation($tables, $operation);

            $progress?->__invoke();
        }

        return new Schema($tables);
    }

    /**
     * @param  array<string, TableSchema>  $tables
     */
    private function applyOperation(array &$tables, SchemaOperation $operation): void
    {
        $declared = isset($tables[$operation->tableName]);

        // prevent the builder from manufacturing a table out of a conditional alter.
        if ($operation->type === SchemaOperationType::Alter && ! $declared) {
            return;
        }

        if ($operation->type === SchemaOperationType::Drop) {
            unset($tables[$operation->tableName]);

            return;
        }

        if ($operation->type === SchemaOperationType::Rename) {
            if ($declared && $operation->renameTo !== null) {
                $this->applyRenameTable($tables, $operation->tableName, $operation->renameTo);
            }

            return;
        }

        $table = $tables[$operation->tableName] ?? TableSchema::make(
            name: $operation->tableName,
            location: $operation->location,
            guard: $operation->guard
        );

        foreach ($operation->chains as $chain) {
            $this->applyChain($table, $chain, $operation->guard);
        }

        $tables[$operation->tableName] = $table;
    }

    /**
     * @param  array<string, TableSchema>  $tables
     */
    private function applyRenameTable(array &$tables, string $from, string $to): void
    {
        $renamed = $tables[$from];
        unset($tables[$from]);

        $tables[$to] = $renamed->renamedTo($to);

        foreach ($tables as $table) {
            $table->renameReferencedTable($from, $to);
        }
    }

    private function shouldApply(ColumnChain $chain, ?SchemaGuard $guard = null): bool
    {
        if ($guard !== SchemaGuard::MissingColumn && $guard !== SchemaGuard::Unknown) {
            return true;
        }

        $root = $chain->root();

        // Column definitions are still applied.
        if (ColumnMethod::tryFrom($root->method) !== null) {
            return true;
        }

        $structuralMethod = StructuralMethod::tryFrom($root->method);

        // With an unknown condition, destructive operations must still be
        // applied so the folded schema does not retain structures that may
        // actually be removed by the migration.
        if ($guard === SchemaGuard::Unknown && $structuralMethod?->isDestructive()) {
            return true;
        }

        // Don't manufacture indexes or foreign keys whose creation is
        // conditional and therefore cannot be established statically.
        return match ($structuralMethod) {
            StructuralMethod::Index,
            StructuralMethod::Unique,
            StructuralMethod::FullText,
            StructuralMethod::Foreign => false,

            default => true,
        };
    }

    private function applyChain(TableSchema $table, ColumnChain $chain, ?SchemaGuard $guard = null): void
    {
        $root = $chain->root();

        if (! $this->shouldApply($chain, $guard)) {
            return;
        }

        if (ColumnMethod::tryFrom($root->method) !== null) {
            $this->applyColumnDefinition($table, $chain, $guard);

            return;
        }

        match (StructuralMethod::tryFrom($root->method)) {
            StructuralMethod::Unique,
            StructuralMethod::Index,
            StructuralMethod::FullText                    => $table->addIndex($this->indexes->resolveTableIndex($root, $table, $guard)),
            StructuralMethod::Primary                     => $table->setPrimaryKey($this->primaryKeys->resolveTablePrimaryKey($root, $guard)),
            StructuralMethod::Foreign                     => $this->applyOldStyleForeign($table, $chain, $guard),
            StructuralMethod::DropColumn                  => $this->applyDropColumn($table, $root),
            StructuralMethod::RenameColumn                => $this->applyRenameColumn($table, $root),
            StructuralMethod::RenameIndex                 => $this->applyRenameIndex($table, $root),
            StructuralMethod::DropIndex                   => $this->applyDropIndex($table, $root, 'index'),
            StructuralMethod::DropUnique                  => $this->applyDropIndex($table, $root, 'unique'),
            StructuralMethod::DropPrimary                 => $this->applyDropIndex($table, $root, 'primary'),
            StructuralMethod::DropFullText                => $this->applyDropIndex($table, $root, 'fulltext'),
            StructuralMethod::DropSpatialIndex            => $this->applyDropIndex($table, $root, 'spatialIndex'),
            StructuralMethod::DropForeign                 => $this->applyDropForeignKey($table, $root),
            StructuralMethod::DropConstrainedForeignId    => $this->applyDropConstrainedIndex($table, $root, 'foreign'),
            StructuralMethod::DropForeignIdFor            => $this->applyDropForeignIdFor($table, $root),
            StructuralMethod::DropConstrainedForeignIdFor => $this->applyDropForeignIdFor($table, $root, true),
            StructuralMethod::DropTimestamps,
            StructuralMethod::DropTimestampsTz  => $table->dropColumns(['created_at', 'updated_at']),
            StructuralMethod::DropRememberToken => $table->dropColumn('remember_token'),
            StructuralMethod::DropSoftDeletes   => $this->applyDropColumn($table, $root, 'deleted_at'),
            null                                => null,
        };
    }

    private function applyColumnDefinition(TableSchema $table, ColumnChain $chain, ?SchemaGuard $guard = null): void
    {
        // Indexes and foreign keys created under `!Schema::hasColumn()` are
        // conditional. Model the column itself, but skip dependent structures
        // to avoid false-positive audit findings.
        if ($guard === SchemaGuard::MissingColumn) {
            return;
        }

        $column = $this->columns->resolve($chain, $guard);

        if (! $column instanceof Column) {
            return;
        }

        $table->addColumn($column);

        if ($column->method->impliesPrimaryKey() || $chain->hasModifier('primary')) {
            $primaryKey = $this->primaryKeys->resolveColumnPrimaryKey($chain, $column, $guard);
            $table->setPrimaryKey($primaryKey);
        }

        if ($chain->hasModifier('constrained')) {
            $resolved = $this->foreignKeys->resolve($chain, $table, $column->name, $guard);
            if ($resolved instanceof ForeignKey) {
                $table->addForeignKey(
                    $resolved->withGuard($guard)
                );
            }
        }

        $this->applyColumnIndex($table, $chain, $column->name, $guard);
    }

    private function applyColumnIndex(
        TableSchema $table,
        ColumnChain $chain,
        string $column,
        ?SchemaGuard $guard = null
    ): void {
        // Laravel materialises unique()->index() as a unique index only.
        // Do not model the chained index() call separately.
        $modifier = $chain->hasModifier('unique') ? 'unique' : 'index';

        $call = $chain->modifier($modifier);

        if ($call instanceof ColumnCall) {
            $table->addIndex(
                $this->indexes->resolveColumnIndex($call, $table, $column, $guard)
            );
        }
    }

    private function applyOldStyleForeign(
        TableSchema $table,
        ColumnChain $chain,
        ?SchemaGuard $guard = null
    ): void {
        $root = $chain->root();

        $columns = $root->stringOrArrayArgument(
            'columns',
            $root->argument(0)
        );

        if ($columns === null) {
            return;
        }

        $name = $root->stringArgument(
            'name',
            $root->stringArgument(1)
        ) ?? $this->conventions->indexName(
            $table->name,
            (array) $columns,
            'foreign'
        );

        $onCall = $chain->modifier('on');
        $referencesCall = $chain->modifier('references');

        $referencesTable = $onCall?->stringArgument('table', $onCall->stringArgument(0));

        $referencesColumn = $referencesCall?->stringOrArrayArgument('columns', $referencesCall->argument(0));

        if ($referencesTable === null || $referencesColumn === null) {
            return;
        }

        $table->addForeignKey(new ForeignKey(
            columns: $columns,
            referencesTable: $referencesTable,
            referencesColumn: $referencesColumn,
            name: $name,
            location: $root->location,
            guard: $guard
        ));
    }

    private function applyDropColumn(TableSchema $table, ColumnCall $call, ?string $defaultColumn = null): void
    {
        $columns = $call->argument(0, $defaultColumn);

        if ($columns === null) {
            return;
        }

        $table->dropColumns(
            is_array($columns) ? $columns : [$columns]
        );
    }

    private function applyRenameIndex(TableSchema $table, ColumnCall $call): void
    {
        $from = $call->stringArgument(0);
        $to = $call->stringArgument(1);

        if ($from === null || $to === null) {
            return;
        }

        $table->renameIndex($from, $to);
    }

    private function applyRenameColumn(TableSchema $table, ColumnCall $call): void
    {
        $from = $call->stringArgument(0);
        $to = $call->stringArgument(1);

        if ($from === null || $to === null) {
            return;
        }

        $table->renameColumn($from, $to);
    }

    private function resolveDropIndexName(
        TableSchema $table,
        ColumnCall $call,
        string $type,
    ): ?string {
        $index = $call->stringOrArrayArgument(0);

        if ($index === null || $index === []) {
            return null;
        }

        return is_string($index)
            ? $index
            : $this->conventions->indexName(
                $table->name,
                $index,
                $type,
            );
    }

    private function applyDropForeignKey(
        TableSchema $table,
        ColumnCall $call,
    ): void {
        $name = $this->resolveDropIndexName($table, $call, 'foreign');

        if ($name !== null) {
            $table->removeForeignKey($name);
        }
    }

    private function applyDropIndex(
        TableSchema $table,
        ColumnCall $call,
        string $type,
    ): void {
        $name = $this->resolveDropIndexName($table, $call, $type);

        if ($name !== null) {
            $table->removeIndex($name);
        }
    }

    private function applyDropConstrainedIndex(TableSchema $table, ColumnCall $call, string $type): void
    {
        $this->applyDropIndex($table, $call, $type);
        $this->applyDropColumn($table, $call);
    }

    private function applyDropForeignIdFor(TableSchema $table, ColumnCall $call, bool $constrained = false): void
    {
        $column = $call->stringArgument('column', $call->stringArgument(1));
        $model = $call->argument('model', $call->argument(0));

        if ($column === null && (is_string($model) || is_object($model))) {
            $column = $this->conventions->foreignKeyColumnFromModel(class_basename($model));
        }

        if (! is_string($column)) {
            return;
        }

        if ($constrained) {
            $table->removeIndex(
                $this->conventions->indexName(
                    $table->name,
                    [$column],
                    'foreign',
                )
            );
        }

        $table->dropColumn($column);
    }
}
