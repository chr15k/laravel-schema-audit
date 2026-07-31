<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Resolvers;

use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnCall;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnChain;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Chr15k\SchemaAudit\Schema\ValueObjects\PrimaryKey;

final readonly class PrimaryKeyResolver
{
    public function resolveTablePrimaryKey(ColumnCall $call): PrimaryKey
    {
        $columns = $call->argument('columns')
            ?? $call->argument(0)
            ?? [];

        return new PrimaryKey(
            columns: (array) $columns
        );
    }

    public function resolveColumnPrimaryKey(ColumnChain $chain, Column $column): PrimaryKey
    {
        $root = $chain->root();

        if ($column->method->impliesPrimaryKey()) {
            return new PrimaryKey([$root->argument(0) ?? 'id']);
        }

        if ($chain->hasModifier('primary')) {
            return new PrimaryKey([$column->name]);
        }

        return new PrimaryKey(columns: ['id']);
    }
}
