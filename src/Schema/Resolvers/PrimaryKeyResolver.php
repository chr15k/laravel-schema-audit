<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Resolvers;

use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnCall;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnChain;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Chr15k\SchemaAudit\Schema\ValueObjects\PrimaryKey;

final readonly class PrimaryKeyResolver
{
    public function resolveTablePrimaryKey(
        ColumnCall $call,
        ?SchemaGuard $guard = null
    ): PrimaryKey {
        $columns = $call->argument('columns')
            ?? $call->argument(0)
            ?? [];

        return new PrimaryKey(
            columns: (array) $columns,
            location: $call->location,
            conditional: $guard === SchemaGuard::Unknown
        );
    }

    public function resolveColumnPrimaryKey(
        ColumnChain $chain,
        Column $column,
        ?SchemaGuard $guard = null
    ): PrimaryKey {
        $root = $chain->root();

        if ($column->method->impliesPrimaryKey()) {
            return new PrimaryKey(
                columns: [$root->argument(0) ?? 'id'],
                location: $root->location,
                conditional: $guard === SchemaGuard::Unknown
            );
        }

        if ($chain->hasModifier('primary')) {
            return new PrimaryKey(
                columns: [$column->name],
                location: $root->location,
                conditional: $guard === SchemaGuard::Unknown
            );
        }

        return new PrimaryKey(
            columns: ['id'],
            location: $root->location,
            conditional: $guard === SchemaGuard::Unknown
        );
    }
}
