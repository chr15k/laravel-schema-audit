<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Resolvers;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnCall;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnChain;
use Chr15k\SchemaAudit\Schema\LaravelConventions;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;

final readonly class ForeignKeyResolver
{
    public function __construct(
        private LaravelConventions $conventions
    ) {}

    public function resolve(
        ColumnChain $chain,
        TableSchema $table,
        string $column
    ): ?ForeignKey {
        $root = $chain->root();
        $modifier = $chain->modifier('constrained');

        if (! $modifier instanceof ColumnCall) {
            return null;
        }

        $method = ColumnMethod::tryFrom($root->method);

        $tableName = $modifier->stringArgs['table']
            ?? $modifier->stringArgs[0]
            ?? (
                $method?->requiresModel()
                    ? $this->conventions->tableNameFromModel($root->stringArgs[0])
                    : $this->conventions->tableNameFromForeignKey($column)
            );

        $name = $modifier->stringArgs['indexName']
            ?? $modifier->stringArgs[2]
            ?? $this->conventions->indexName(
                $table->name,
                [$column],
                'foreign',
            );

        $referencesColumn = $modifier->stringArgs['column']
            ?? $modifier->stringArgs[1]
            ?? 'id'; // constrained()'s own default when nothing is specified

        return new ForeignKey(
            column: $column,
            referencesTable: $tableName,
            referencesColumn: $referencesColumn,
            name: $name
        );
    }
}
