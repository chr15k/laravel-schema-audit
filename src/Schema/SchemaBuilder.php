<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
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

        if ($declared && $operation->guard === SchemaGuard::Unknown) {
            $tables[$operation->tableName]->markConditional();
        }

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

        $table = $tables[$operation->tableName] ?? TableSchema::make(
            name: $operation->tableName,
            location: $operation->location,
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

        $tables[$to] = TableSchema::make($to);
        $tables[$to]->setLocation($renamed->location());
        $tables[$to]->setConditional($renamed->isConditional());

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

    // Some schema operations are guarded by runtime existence checks.
    // Skip operations that cannot be inferred statically to avoid
    // false-positive audit findings.
    private function shouldApply(ColumnChain $chain, ?SchemaGuard $guard = null): bool
    {
        if ($guard !== SchemaGuard::MissingColumn) {
            return true;
        }

        $root = $chain->root();

        // Column definitions are still applied.
        if (ColumnMethod::tryFrom($root->method) !== null) {
            return true;
        }

        // Skip standalone indexes and foreign keys.
        return match (StructuralMethod::tryFrom($root->method)) {
            StructuralMethod::Index,
            StructuralMethod::Unique,
            StructuralMethod::FullText,
            StructuralMethod::Foreign => false,

            default => true,
        };
    }

    private function applyChain(TableSchema $table, ColumnChain $chain, ?SchemaGuard $guard = null): void
    {
        if (! $this->shouldApply($chain, $guard)) {
            return;
        }

        $root = $chain->root();

        if (ColumnMethod::tryFrom($root->method) !== null) {
            $this->applyColumnDefinition($table, $chain, $guard);

            return;
        }

        match (StructuralMethod::tryFrom($root->method)) {
            StructuralMethod::Unique,
            StructuralMethod::Index,
            StructuralMethod::FullText                                => $table->addIndex($this->indexes->resolveTableIndex($root, $table, $guard)),
            StructuralMethod::Foreign                                 => $this->applyOldStyleForeign($table, $chain, $guard),
            StructuralMethod::DropColumn                              => $this->applyDropColumn($table, $root),
            StructuralMethod::RenameColumn                            => $this->applyRenameColumn($table, $root),
            StructuralMethod::DropIndex, StructuralMethod::DropUnique => $this->applyDropIndex($table, $root),
            StructuralMethod::DropForeign                             => $this->applyDropForeign($table, $root),
            StructuralMethod::Primary                                 => $table->setPrimaryKey($this->primaryKeys->resolveTablePrimaryKey($root, $guard)),
            null                                                      => null, // not a known structural method — dropPrimary(), timestamps(), etc.
        };
    }

    private function applyDropForeign(TableSchema $table, ColumnCall $call): void
    {
        $index = $call->argument(0);

        if (is_array($index)) {
            $index = $this->conventions->indexName(
                $table->name,
                $index,
                'foreign'
            );
        }

        if (is_string($index)) {
            $table->removeForeignKey($index);
        }
    }

    private function applyColumnDefinition(TableSchema $table, ColumnChain $chain, ?SchemaGuard $guard = null): void
    {
        $column = $this->columns->resolve($chain, $guard);

        if (! $column instanceof Column) {
            return;
        }

        $table->addColumn($column);

        // Indexes and foreign keys created under `!Schema::hasColumn()` are
        // conditional. Model the column itself, but skip dependent structures
        // to avoid false-positive audit findings.
        if ($guard === SchemaGuard::MissingColumn) {
            return;
        }

        if ($column->method->impliesPrimaryKey() || $chain->hasModifier('primary')) {
            $primaryKey = $this->primaryKeys->resolveColumnPrimaryKey($chain, $column, $guard);
            $table->setPrimaryKey($primaryKey);
        }

        if ($chain->hasModifier('constrained')) {
            $resolved = $this->foreignKeys->resolve($chain, $table, $column->name, $guard);
            if ($resolved instanceof ForeignKey) {
                $table->addForeignKey(
                    $resolved->withConditional($guard === SchemaGuard::Unknown)
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

    private function applyOldStyleForeign(TableSchema $table, ColumnChain $chain, ?SchemaGuard $guard = null): void
    {
        $root = $chain->root();
        $column = $root->argument(0);

        if ($column === null) {
            return;
        }

        $onCall = $chain->modifier('on');
        $referencesCall = $chain->modifier('references');

        $referencesTable = $onCall?->arguments[0] ?? null;
        $referencesColumn = $referencesCall?->arguments[0] ?? null;

        if ($referencesTable === null || $referencesColumn === null) {
            return;
        }

        $constraintName = $root->arguments[1] ?? null;

        $table->addForeignKey(new ForeignKey(
            column: $column,
            referencesTable: $referencesTable,
            referencesColumn: $referencesColumn,
            name: $constraintName,
            location: $root->location,
            conditional: $guard === SchemaGuard::Unknown
        ));
    }

    private function applyDropColumn(TableSchema $table, ColumnCall $call): void
    {
        $columns = $call->argument(0);

        if (! is_array($columns)) {
            $columns = [$columns];
        }

        foreach ($columns as $column) {
            if (is_string($column)) {
                $table->dropColumn($column);
            }
        }
    }

    private function applyRenameColumn(TableSchema $table, ColumnCall $call): void
    {
        $from = $call->argument(0);
        $to = $call->argument(1);

        if ($from === null || $to === null) {
            return;
        }

        $table->renameColumn($from, $to);
    }

    private function applyDropIndex(TableSchema $table, ColumnCall $call): void
    {
        $index = $call->argument(0);

        if (! is_array($index)) {
            $index = [$index];
        }

        foreach ($index as $name) {
            if (is_string($name)) {
                $table->removeIndex($name);
            }
        }
    }
}
