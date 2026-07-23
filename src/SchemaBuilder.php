<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaOperationType;
use Chr15k\SchemaAudit\Enums\StructuralMethod;
use Chr15k\SchemaAudit\Parsers\MigrationParser;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnCall;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnChain;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SchemaOperation;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;

final readonly class SchemaBuilder
{
    public function __construct(private MigrationParser $parser) {}

    public function buildFromDirectory(string $migrationsPath): Schema
    {
        $files = glob(mb_rtrim($migrationsPath, '/').'/*.php') ?: [];
        sort($files);

        /** @var array<string, TableSchema> $tables */
        $tables = [];

        foreach ($files as $file) {
            foreach ($this->parser->parseFile($file) as $operation) {
                $this->applyOperation($tables, $operation);
            }
        }

        return new Schema($tables);
    }

    /**
     * @param  array<string, TableSchema>  $tables
     */
    private function applyOperation(array &$tables, SchemaOperation $operation): void
    {
        // prevent the builder from manufacturing a table out of a conditional alter.
        if ($operation->type === SchemaOperationType::Alter && ! isset($tables[$operation->tableName])) {
            return;
        }

        if ($operation->type === SchemaOperationType::Drop) {
            unset($tables[$operation->tableName]);

            return;
        }

        if ($operation->type === SchemaOperationType::Rename) {
            if (isset($tables[$operation->tableName]) && $operation->renameTo !== null) {
                $renamed = $tables[$operation->tableName];
                unset($tables[$operation->tableName]);

                $tables[$operation->renameTo] = new TableSchema($operation->renameTo);

                foreach ($renamed->columns() as $column) {
                    $tables[$operation->renameTo]->addColumn($column);
                }

                foreach ($renamed->indexes() as $index) {
                    $tables[$operation->renameTo]->addIndex($index);
                }

                foreach ($renamed->foreignKeys() as $fk) {
                    $tables[$operation->renameTo]->addForeignKey($fk);
                }
            }

            return;
        }

        $table = $tables[$operation->tableName] ?? new TableSchema($operation->tableName);

        foreach ($operation->chains as $chain) {
            $this->applyChain($table, $chain);
        }

        $tables[$operation->tableName] = $table;
    }

    private function applyChain(TableSchema $table, ColumnChain $chain): void
    {
        $root = $chain->root();

        if (ColumnMethod::tryFrom($root->method) !== null) {
            $this->applyColumnDefinition($table, $chain);

            return;
        }

        match (StructuralMethod::tryFrom($root->method)) {
            StructuralMethod::Foreign                                 => $this->applyOldStyleForeign($table, $chain),
            StructuralMethod::Unique                                  => $table->addIndex($this->buildTableLevelIndex($root, unique: true)),
            StructuralMethod::Index                                   => $table->addIndex($this->buildTableLevelIndex($root, unique: false)),
            StructuralMethod::FullText                                => $table->addIndex($this->buildTableLevelIndex($root, unique: false)),
            StructuralMethod::DropColumn                              => $this->applyDropColumn($table, $root),
            StructuralMethod::RenameColumn                            => $this->applyRenameColumn($table, $root),
            StructuralMethod::DropIndex, StructuralMethod::DropUnique => $this->applyDropIndex($table, $root),
            StructuralMethod::DropForeign                             => $this->applyDropForeign($table, $root),
            StructuralMethod::Primary                                 => $table->markPrimaryKey(),
            null                                                      => null, // not a known structural method — dropPrimary(), timestamps(), etc.
        };
    }

    private function applyDropForeign(TableSchema $table, ColumnCall $call): void
    {
        foreach (($call->stringArgs !== [] ? $call->stringArgs : $call->arrayArgs) as $nameOrColumn) {
            $table->dropForeignKey($nameOrColumn);
        }
    }

    private function applyColumnDefinition(TableSchema $table, ColumnChain $chain): void
    {
        $root = $chain->root();
        $method = ColumnMethod::tryFrom($root->method);
        $impliedPrimaryKey = $method?->impliesAutoIncrementingPrimaryKey() ?? false;

        $name = $root->stringArgs[0] ?? ($impliedPrimaryKey ? 'id' : null);

        if ($name === null) {
            return;
        }

        $method ??= ColumnMethod::tryFrom($name);

        if ($method === null) {
            return;
        }

        // @todo - currently guessing (need to parse models)...
        if ($method->isForeignIdType() && str_contains($name, '::class')) {
            $name = str($name)
                ->before('::class')
                ->singular()
                ->lower()
                ->append('_id')
                ->toString();
        }

        $table->addColumn(new Column($name, $method));

        if ($chain->hasModifier('primary') || $impliedPrimaryKey) {
            $table->markPrimaryKey();
        }

        if ($method->isForeignIdType() && $chain->hasModifier('constrained')) {
            $constrained = $chain->modifier('constrained');

            // @todo - resolve referenced table if no stringArgs
            $referencesTable = $constrained?->stringArgs[0] ?? null;

            $table->addForeignKey(new ForeignKey(column: $name, referencesTable: $referencesTable));
        }

        if ($chain->hasModifier('unique')) {
            $table->addIndex(new Index(name: null, columns: [$name], unique: true));
        }

        if ($chain->hasModifier('index')) {
            $table->addIndex(new Index(name: null, columns: [$name], unique: false));
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
        $referencesTable = $onCall?->stringArgs[0] ?? null;
        $constraintName = $root->stringArgs[1] ?? null;

        $table->addForeignKey(new ForeignKey(column: $column, referencesTable: $referencesTable, name: $constraintName));
    }

    private function buildTableLevelIndex(ColumnCall $call, bool $unique): Index
    {
        if ($call->arrayArgs !== []) {
            $name = $call->stringArgs[0] ?? null;

            return new Index(name: $name, columns: $call->arrayArgs, unique: $unique);
        }

        // $table->unique('col') or $table->unique('col', 'name')
        $columns = $call->stringArgs !== [] ? [$call->stringArgs[0]] : [];
        $name = $call->stringArgs[1] ?? null;

        return new Index(name: $name, columns: $columns, unique: $unique);
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
            $table->dropIndex($name);
        }
    }
}
