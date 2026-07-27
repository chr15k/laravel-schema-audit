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
        $columns = $call->arrayArgs === []
            ? ($call->stringArgs['columns']
                ?? $call->stringArgs[0]
                ?? [])
            : $call->arrayArgs;

        $columns = (array) $columns;

        return new PrimaryKey(columns: $columns);
    }

    public function resolveColumnPrimaryKey(ColumnChain $chain, Column $column): PrimaryKey
    {
        $root = $chain->root();

        // 1. implied  ($table->id() | $table->increments('id') etc.)
        if ($column->method->impliesPrimaryKey()) {
            return new PrimaryKey([$root->stringArgs[0] ?? 'id']);
        }

        // 2. modifier ($table->string('uuid')->primary())
        if ($chain->hasModifier('primary')) {
            return new PrimaryKey([$column->name]);
        }

        return new PrimaryKey(columns: ['id']);
    }
}
