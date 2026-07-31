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
        $columns = $call->argument('columns')
            ?? $call->argument(0)
            ?? [];

        $columns = (array) $columns;

        return new Index(
            name: $call->argument('name')
                ?? $call->argument(1)
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
        return new Index(
            name: $call->argument('indexName')
                ?? $call->argument(0)
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
