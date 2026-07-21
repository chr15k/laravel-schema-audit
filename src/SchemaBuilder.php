<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaOperationType;
use Chr15k\SchemaAudit\ValueObjects\ColumnCall;
use Chr15k\SchemaAudit\ValueObjects\ColumnChain;
use Chr15k\SchemaAudit\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\ValueObjects\Index;
use Chr15k\SchemaAudit\ValueObjects\SchemaOperation;

final readonly class SchemaBuilder
{
    public function __construct(private MigrationParser $parser) {}

    /**
     * @return array<string, TableSchema>
     */
    public function buildFromDirectory(string $migrationsPath): array
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

        return $tables;
    }

    /**
     * @param  array<string, TableSchema>  $tables
     */
    private function applyOperation(array &$tables, SchemaOperation $operation): void
    {
        if ($operation->type === SchemaOperationType::Drop) {
            unset($tables[$operation->tableName]);

            return;
        }

        if ($operation->type === SchemaOperationType::Rename) {
            if (isset($tables[$operation->tableName]) && $operation->renameTo !== null) {
                $renamed = $tables[$operation->tableName];
                unset($tables[$operation->tableName]);

                $tables[$operation->renameTo] = new TableSchema($operation->renameTo);

                foreach ($renamed->columns() as $name => $type) {
                    $tables[$operation->renameTo]->addColumn($name, $type);
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

        match (true) {
            ColumnMethod::tryFrom($root->method) !== null => $this->applyColumnDefinition($table, $chain),
            $root->method === 'foreign'                   => $this->applyOldStyleForeign($table, $chain),
            $root->method === 'unique'                    => $table->addIndex($this->buildTableLevelIndex($root, unique: true)),
            $root->method === 'index'                     => $table->addIndex($this->buildTableLevelIndex($root, unique: false)),
            $root->method === 'fullText'                  => $table->addIndex($this->buildTableLevelIndex($root, unique: false)),
            $root->method === 'dropColumn'                => $this->applyDropColumn($table, $root),
            $root->method === 'renameColumn'              => $this->applyRenameColumn($table, $root),
            $root->method === 'dropIndex'                 => $this->applyDropIndex($table, $root),
            $root->method === 'dropUnique'                => $this->applyDropIndex($table, $root),
            $root->method === 'dropForeign'               => $this->applyDropForeign($table, $root),
            $root->method === 'primary'                   => $table->markPrimaryKey(),
            default                                       => null, // dropPrimary(), timestamps(), etc. — no schema-shape impact we track
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
        $defaultsToId = $root->method->impliesAutoIncrementingPrimaryKey() ?? false;
        $name = $root->stringArgs[0] ?? ($defaultsToId ? 'id' : null);

        if ($name === null) {
            return;
        }

        $table->addColumn($name, $root->method);

        if (in_array($root->method, ['foreignId', 'foreignUuid', 'foreignUlid'], true) && $chain->hasModifier('constrained')) {
            $constrained = $chain->modifier('constrained');
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
