<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Data\ColumnCall;
use Chr15k\SchemaAudit\Data\ColumnChain;
use Chr15k\SchemaAudit\Data\ForeignKey;
use Chr15k\SchemaAudit\Data\Index;
use Chr15k\SchemaAudit\Data\SchemaOperation;

/**
 * Folds every migration file's SchemaOperations, in filename order, into a
 * final map of table name => TableSchema. Laravel migration filenames are
 * timestamp-prefixed (YYYY_MM_DD_HHMMSS_description.php), so a plain
 * alphabetical sort of the directory listing is already chronological —
 * this is the ordering assumption the whole tool depends on.
 */
final readonly class SchemaBuilder
{
    /**
     * Real Blueprint column-defining methods. Deliberately a WHITELIST,
     * not a blacklist of "structural" methods — an earlier blacklist
     * approach silently treated chain continuations like ->references(),
     * ->on(), ->onDelete(), ->onUpdate(), ->dropForeign() as if they were
     * columns (e.g. $table->references('id') was read as "a column named
     * id of type references", overwriting the real id column). A
     * whitelist can only miss column types we haven't listed — it can
     * never misinterpret a non-column chain call as one.
     *
     * @var list<string>
     */
    private const COLUMN_METHODS = [
        'id', 'increments', 'bigIncrements', 'smallIncrements', 'mediumIncrements',
        'integer', 'tinyInteger', 'smallInteger', 'mediumInteger', 'bigInteger',
        'unsignedInteger', 'unsignedTinyInteger', 'unsignedSmallInteger',
        'unsignedMediumInteger', 'unsignedBigInteger',
        'float', 'double', 'decimal', 'unsignedDecimal',
        'string', 'char', 'text', 'tinyText', 'mediumText', 'longText',
        'boolean', 'enum', 'set', 'json', 'jsonb',
        'date', 'dateTime', 'dateTimeTz', 'time', 'timeTz',
        'timestamp', 'timestampTz', 'softDeletes', 'softDeletesTz', 'year',
        'binary', 'uuid', 'ulid', 'ipAddress', 'macAddress',
        'geometry', 'geography', 'point', 'lineString', 'polygon',
        'morphs', 'nullableMorphs', 'uuidMorphs', 'ulidMorphs',
        'foreignId', 'foreignUuid', 'foreignUlid', 'foreignIdFor',
    ];

    /**
     * Blueprint methods that default to a column named 'id' when called
     * with no arguments — e.g. $table->id() is equivalent to
     * $table->id('id'), and is by far the more common form in practice.
     * Without this, $table->id() (no arg) was silently dropped entirely,
     * which meant tables using the idiomatic $table->id() were incorrectly
     * flagged as having no primary key.
     *
     * @var list<string>
     */
    private const DEFAULTS_TO_ID_COLUMN = ['id', 'increments', 'bigIncrements', 'smallIncrements', 'mediumIncrements'];

    public function __construct(
        private MigrationParser $parser = new MigrationParser,
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
        if ($operation->type === SchemaOperation::TYPE_DROP) {
            // A dropped table's history shouldn't leak into whatever gets
            // created under the same name later — reset completely rather
            // than leaving stale columns/indexes/FKs to accumulate onto.
            unset($tables[$operation->tableName]);

            return;
        }

        if ($operation->type === SchemaOperation::TYPE_RENAME) {
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
            in_array($root->method, self::COLUMN_METHODS, true) => $this->applyColumnDefinition($table, $chain),
            $root->method === 'foreign'                         => $this->applyOldStyleForeign($table, $chain),
            $root->method === 'unique'                          => $table->addIndex($this->buildTableLevelIndex($root, unique: true)),
            $root->method === 'index'                           => $table->addIndex($this->buildTableLevelIndex($root, unique: false)),
            $root->method === 'fullText'                        => $table->addIndex($this->buildTableLevelIndex($root, unique: false)),
            $root->method === 'dropColumn'                      => $this->applyDropColumn($table, $root),
            $root->method === 'renameColumn'                    => $this->applyRenameColumn($table, $root),
            $root->method === 'dropIndex'                       => $this->applyDropIndex($table, $root),
            $root->method === 'dropUnique'                      => $this->applyDropIndex($table, $root),
            $root->method === 'dropForeign'                     => $this->applyDropForeign($table, $root),
            $root->method === 'primary'                         => $table->markPrimaryKey(),
            default                                             => null, // dropPrimary(), timestamps(), etc. — no schema-shape impact we track
        };
    }

    private function applyDropForeign(TableSchema $table, ColumnCall $call): void
    {
        // dropForeign('constraint_name') most commonly; dropForeign(['column'])
        // is also valid Laravel syntax (matches by column via convention).
        foreach (($call->stringArgs !== [] ? $call->stringArgs : $call->arrayArgs) as $nameOrColumn) {
            $table->dropForeignKey($nameOrColumn);
        }
    }

    private function applyColumnDefinition(TableSchema $table, ColumnChain $chain): void
    {
        $root = $chain->root();
        $name = $root->stringArgs[0] ?? (in_array($root->method, self::DEFAULTS_TO_ID_COLUMN, true) ? 'id' : null);

        if ($name === null) {
            return; // e.g. $table->timestamps() has no name arg — not tracked as a single column
        }

        $table->addColumn($name, $root->method);

        // foreignId('x')->constrained() implies a foreign key even though
        // the referenced table isn't explicit in the constrained() call
        // itself (Laravel infers it from the column name by convention).
        if (in_array($root->method, ['foreignId', 'foreignUuid', 'foreignUlid'], true) && $chain->hasModifier('constrained')) {
            $constrained = $chain->modifier('constrained');
            $referencesTable = $constrained?->stringArgs[0] ?? null;
            $table->addForeignKey(new ForeignKey(column: $name, referencesTable: $referencesTable));
        }

        // A column-level ->unique() or ->index() modifier (no args, chained
        // directly onto the column definition) creates a single-column
        // index on THIS column — distinct from a table-level $table->unique(...)
        // call, which is why this only fires from inside a column chain.
        if ($chain->hasModifier('unique')) {
            $table->addIndex(new Index(name: null, columns: [$name], unique: true));
        }

        if ($chain->hasModifier('index')) {
            $table->addIndex(new Index(name: null, columns: [$name], unique: false));
        }
    }

    /**
     * $table->foreign('col')->references('id')->on('other_table')->onDelete(...)->onUpdate(...)
     * The optional second string arg on foreign() itself is a constraint
     * NAME, not a second column — a mistake the old flat-call parser made.
     */
    private function applyOldStyleForeign(TableSchema $table, ColumnChain $chain): void
    {
        $root = $chain->root();
        $column = $root->stringArgs[0] ?? null;

        if ($column === null) {
            return;
        }

        $onCall = $chain->modifier('on');
        $referencesTable = $onCall?->stringArgs[0] ?? null;

        // $table->foreign('col', 'constraint_name') — optional second
        // string arg on the root call is the constraint name, allowing
        // a later dropForeign('constraint_name') to find and remove it.
        $constraintName = $root->stringArgs[1] ?? null;

        $table->addForeignKey(new ForeignKey(column: $column, referencesTable: $referencesTable, name: $constraintName));

        // references()/on()/onDelete()/onUpdate() carry no independent
        // schema meaning beyond building this one ForeignKey — intentionally
        // not applied as column definitions, unlike the old blacklist parser.
    }

    /**
     * Table-level index/unique/fullText call. The first positional arg is
     * either a single column string or an array of columns; if a SECOND
     * string arg is present it is an explicit index NAME, never an
     * additional column — e.g. $table->index('property_id', 'property_id')
     * names the index 'property_id', it does not index two columns.
     */
    private function buildTableLevelIndex(ColumnCall $call, bool $unique): Index
    {
        if ($call->arrayArgs !== []) {
            // $table->unique(['a', 'b'], 'optional_name') — arrayArgs holds
            // the columns; any string arg alongside it is the name, not
            // captured in arrayArgs since it's parsed from a separate Arg.
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
        // Laravel allows dropIndex(['col1', 'col2']) as an alternative to
        // an explicit name (it derives the conventional name internally).
        // We only support the explicit-name string form for now — the
        // array form is silently ignored rather than guessed at, since a
        // wrong guess here would incorrectly un-index a real index.
        foreach ($call->stringArgs as $name) {
            $table->dropIndex($name);
        }
    }
}
