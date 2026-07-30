<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaOperationType;
use Chr15k\SchemaAudit\Enums\StructuralMethod;
use Chr15k\SchemaAudit\Parsers\MigrationParser;
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
        private MigrationParser $parser,
        private IndexResolver $indexes,
        private ForeignKeyResolver $foreignKeys,
        private ColumnResolver $columns,
        private PrimaryKeyResolver $primaryKeys,
        private LaravelConventions $conventions
    ) {}

    /**
     * @param  array<string>  $files
     */
    public function build(array $files, ?Closure $progress = null): Schema
    {
        /** @var array<string, TableSchema> $tables */
        $tables = [];

        foreach ($files as $file) {
            foreach ($this->parser->parseFile($file) as $operation) {
                $this->applyOperation($tables, $operation);
            }

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
            if ($declared) {
                unset($tables[$operation->tableName]);
            }

            return;
        }

        if ($operation->type === SchemaOperationType::Rename) {
            if ($declared && $operation->renameTo !== null) {
                $this->applyRenameTable($tables, $operation->tableName, $operation->renameTo);
            }

            return;
        }

        $table = $tables[$operation->tableName] ?? TableSchema::make($operation->tableName);

        foreach ($operation->chains as $chain) {
            $this->applyChain($table, $chain);
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

        $tables[$to] = TableSchema::make($to);

        if ($pk = $renamed->primaryKey()) {
            $tables[$to]->setPrimaryKey($pk);
        }

        foreach ($renamed->columns() as $column) {
            $tables[$to]->addColumn($column);
        }

        foreach ($renamed->indexes() as $index) {
            $tables[$to]->addIndex($index);
        }

        foreach ($renamed->foreignKeys() as $fk) {
            $tables[$to]->addForeignKey($fk);
        }

        foreach ($tables as $table) {
            $table->renameReferencedTable($from, $to);
        }
    }

    private function applyChain(TableSchema $table, ColumnChain $chain): void
    {
        $root = $chain->root();

        if (ColumnMethod::tryFrom($root->method) !== null) {
            $this->applyColumnDefinition($table, $chain);

            return;
        }

        match (StructuralMethod::tryFrom($root->method)) {
            StructuralMethod::Unique,
            StructuralMethod::Index,
            StructuralMethod::FullText                                => $table->addIndex($this->indexes->resolveTableIndex($root, $table)),
            StructuralMethod::Foreign                                 => $this->applyOldStyleForeign($table, $chain),
            StructuralMethod::DropColumn                              => $this->applyDropColumn($table, $root),
            StructuralMethod::RenameColumn                            => $this->applyRenameColumn($table, $root),
            StructuralMethod::DropIndex, StructuralMethod::DropUnique => $this->applyDropIndex($table, $root),
            StructuralMethod::DropForeign                             => $this->applyDropForeign($table, $root),
            StructuralMethod::Primary                                 => $table->setPrimaryKey($this->primaryKeys->resolveTablePrimaryKey($root)),
            null                                                      => null, // not a known structural method — dropPrimary(), timestamps(), etc.
        };
    }

    private function applyDropForeign(TableSchema $table, ColumnCall $call): void
    {
        $index = $call->stringArgs[0] ?? $call->arrayArgs;

        if (is_array($index)) {
            $index = $this->conventions->indexName($table->name, $index, 'foreign');
        }

        $table->removeForeignKey($index);
    }

    private function applyColumnDefinition(TableSchema $table, ColumnChain $chain): void
    {
        $root = $chain->root();

        $column = $this->columns->resolve($root);

        if (! $column instanceof Column) {
            return;
        }

        $table->addColumn($column);

        if ($column->method->impliesPrimaryKey() || $chain->hasModifier('primary')) {
            $primaryKey = $this->primaryKeys->resolveColumnPrimaryKey($chain, $column);
            $table->setPrimaryKey($primaryKey);
        }

        if ($chain->hasModifier('constrained')) {
            $resolved = $this->foreignKeys->resolve($chain, $table, $column->name);
            if ($resolved instanceof ForeignKey) {
                $table->addForeignKey($resolved);
            }
        }

        $this->applyColumnIndex($table, $chain, $column->name);
    }

    private function applyColumnIndex(TableSchema $table, ColumnChain $chain, string $column): void
    {
        // Laravel materialises unique()->index() as a unique index only.
        // Do not model the chained index() call separately.
        $modifier = $chain->hasModifier('unique') ? 'unique' : 'index';

        $call = $chain->modifier($modifier);

        if ($call instanceof ColumnCall) {
            $table->addIndex(
                $this->indexes->resolveColumnIndex($call, $table, $column)
            );
        }
    }

    private function applyOldStyleForeign(TableSchema $table, ColumnChain $chain): void
    {
        $root = $chain->root();
        $column = $root->stringArgs[0] ?? null;

        if ($column === null) {
            return;
        }

        $onCall = $chain->modifier('on');
        $referencesCall = $chain->modifier('references');

        $referencesTable = $onCall?->stringArgs[0] ?? null;
        $referencesColumn = $referencesCall?->stringArgs[0] ?? 'id';
        $constraintName = $root->stringArgs[1] ?? null;

        $table->addForeignKey(new ForeignKey(
            column: $column,
            referencesTable: $referencesTable,
            referencesColumn: $referencesColumn,
            name: $constraintName
        ));
    }

    private function applyDropColumn(TableSchema $table, ColumnCall $call): void
    {
        foreach (($call->stringArgs !== [] ? $call->stringArgs : $call->arrayArgs) as $name) {
            $table->dropColumn($name);
        }
    }

    private function applyRenameColumn(TableSchema $table, ColumnCall $call): void
    {
        $from = $call->stringArgs[0] ?? null;
        $to = $call->stringArgs[1] ?? null;

        if ($from === null || $to === null) {
            return;
        }

        $table->renameColumn($from, $to);
    }

    private function applyDropIndex(TableSchema $table, ColumnCall $call): void
    {
        foreach ($call->stringArgs as $name) {
            $table->removeIndex($name);
        }
    }
}
