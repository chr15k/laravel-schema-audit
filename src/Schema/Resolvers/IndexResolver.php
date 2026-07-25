<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Resolvers;

use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnCall;
use Chr15k\SchemaAudit\Schema\LaravelConventions;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;

final readonly class IndexResolver
{
    public function __construct(
        private LaravelConventions $conventions
    ) {}

    public function resolveTableIndex(
        ColumnCall $call,
        TableSchema $table
    ): Index {
        $columns = $call->arrayArgs === []
            ? ($call->stringArgs['columns']
                ?? $call->stringArgs[0]
                ?? [])
            : $call->arrayArgs;

        $columns = (array) $columns;

        return new Index(
            name: $call->stringArgs['name']
                ?? $call->stringArgs[1]
                ?? $this->conventions->indexName(
                    $table->name,
                    $columns,
                    $call->method
                ),
            columns: $columns,
            unique: $call->method === 'unique'
        );
    }

    public function resolveColumnIndex(
        ColumnCall $call,
        TableSchema $table,
        string $column
    ): Index {
        // Cases...
        //
        // 1. Auto generated index name
        //   $table->string('email')->unique()
        //   $table->string('email')->index()
        //
        // 2. Named parameter
        //   $table->string('email')->unique(indexName: 'idx_unique_email')
        //   $table->string('email')->index(indexName: 'idx_unique_email')
        //
        // 3. Parameter
        //   $table->string('email')->unique('idx_unique_email')
        //   $table->string('email')->index('idx_unique_email')

        return new Index(
            name: $call->stringArgs['indexName']
                ?? $call->stringArgs[0]
                ?? $this->conventions->indexName(
                    $table->name,
                    [$column],
                    $call->method
                ),
            columns: [$column],
            unique: $call->method === 'unique'
        );
    }
}
