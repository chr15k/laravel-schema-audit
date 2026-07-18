<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

/**
 * Folds every migration file's SchemaOperations, in filename order, into a
 * final map of table name => TableSchema. Laravel migration filenames are
 * timestamp-prefixed (YYYY_MM_DD_HHMMSS_description.php), so a plain
 * alphabetical sort of the directory listing is already chronological —
 * this is the ordering assumption the whole tool depends on.
 */
final class SchemaBuilder
{
    public function __construct(
        private readonly MigrationParser $parser = new MigrationParser,
    ) {}

    /**
     * @return array<string, TableSchema> table name => folded schema
     */
    public function buildFromDirectory(string $migrationsPath): array
    {
        $files = glob(mb_rtrim($migrationsPath, '/').'/*.php') ?: [];
        sort($files); // relies on timestamp-prefixed filenames for chronological order

        /** @var array<string, TableSchema> $tables */
        $tables = [];

        $files = (array) array_first($files);

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
        $table = $tables[$operation->tableName] ?? new TableSchema($operation->tableName);

        foreach ($operation->columnCalls as $call) {
            $this->applyColumnCall($table, $call);
        }

        $tables[$operation->tableName] = $table;
    }

    private function applyColumnCall(TableSchema $table, ColumnCall $call): void
    {
        match (true) {
            $this->isColumnDefinition($call->method) => $this->applyColumnDefinition($table, $call),
            $call->method === 'unique'               => $table->addIndex($this->buildIndex($call, unique: true)),
            $call->method === 'index'                => $table->addIndex($this->buildIndex($call, unique: false)),
            $call->method === 'dropColumn'           => $this->applyDropColumn($table, $call),
            $call->method === 'dropIndex'            => $this->applyDropIndex($table, $call),
            $call->method === 'foreignId'            => $this->applyForeignId($table, $call),
            $call->method === 'dropUnique'           => $this->applyDropIndex($table, $call),
            default                                  => null, // nullable(), default(), comment(), etc. — no schema-shape impact we track
        };
    }

    /**
     * Column-defining Blueprint methods: string, integer, boolean, text,
     * decimal, date, timestamp, uuid, etc. We treat "any method whose
     * first string arg names a column" as a column definition unless it's
     * one of the special-cased structural methods above.
     */
    private function isColumnDefinition(string $method): bool
    {
        $structural = ['index', 'unique', 'dropColumn', 'dropIndex', 'dropUnique', 'foreignId', 'foreign', 'primary', 'dropPrimary'];

        return ! in_array($method, $structural, true);
    }

    private function applyColumnDefinition(TableSchema $table, ColumnCall $call): void
    {
        $name = $call->stringArgs[0] ?? null;

        if ($name === null) {
            return; // e.g. $table->timestamps() has no name arg — not tracked as a single column
        }

        $table->addColumn($name, $call->method);
    }

    private function applyForeignId(TableSchema $table, ColumnCall $call): void
    {
        $name = $call->stringArgs[0] ?? null;

        if ($name === null) {
            return;
        }

        $table->addColumn($name, 'foreignId');
        $table->addForeignKey(new ForeignKey(column: $name, referencesTable: null));
    }

    private function buildIndex(ColumnCall $call, bool $unique): Index
    {
        // $table->index(['a', 'b']) -> arrayArgs; $table->index('a') -> stringArgs
        $columns = $call->arrayArgs !== [] ? $call->arrayArgs : $call->stringArgs;

        return new Index(name: null, columns: $columns, unique: $unique);
    }

    private function applyDropColumn(TableSchema $table, ColumnCall $call): void
    {
        foreach (($call->stringArgs !== [] ? $call->stringArgs : $call->arrayArgs) as $name) {
            $table->dropColumn($name);
        }
    }

    private function applyDropIndex(TableSchema $table, ColumnCall $call): void
    {
        foreach ($call->stringArgs as $name) {
            $table->dropIndex($name);
        }
    }
}
