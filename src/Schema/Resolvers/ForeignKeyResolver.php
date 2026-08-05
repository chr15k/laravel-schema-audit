<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Resolvers;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
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
        string $column,
        ?SchemaGuard $guard = null
    ): ?ForeignKey {
        $root = $chain->root();
        $modifier = $chain->modifier('constrained');

        if (! $modifier instanceof ColumnCall) {
            return null;
        }

        $method = ColumnMethod::tryFrom($root->method);

        $tableName = $modifier->stringArgument('table')
            ?? $modifier->stringArgument(0)
            ?? (
                $method?->requiresModel()
                    ? $this->conventions->tableNameFromModel($root->stringArgument(0) ?? '')
                    : $this->conventions->tableNameFromForeignKey($column)
            );

        $name = $modifier->stringArgument('indexName', $modifier->stringArgument(2))
            ?? $this->conventions->indexName(
                $table->name,
                [$column],
                'foreign',
            );

        $referencesColumn = $modifier->stringArgument('column', $modifier->stringArgument(1)) ?? 'id';

        return new ForeignKey(
            columns: $column,
            referencesTable: $tableName,
            referencesColumn: $referencesColumn,
            name: $name,
            location: $root->location,
            guard: $guard
        );
    }
}
