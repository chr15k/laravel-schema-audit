<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Resolvers;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnCall;
use Chr15k\SchemaAudit\Schema\LaravelConventions;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;

final readonly class ColumnResolver
{
    public function __construct(
        private LaravelConventions $conventions
    ) {}

    public function resolve(ColumnCall $call): ?Column
    {
        $method = ColumnMethod::tryFrom($call->method);

        if ($method === null) {
            return null;
        }

        $impliedPrimaryKey = $method->impliesPrimaryKey();

        $name = $call->stringArgs[0] ?? ($impliedPrimaryKey ? 'id' : null);

        if ($name === null) {
            return null;
        }

        if ($method->isForeignIdType() && str_contains($name, '::class')) {
            $name = $call->stringArgs[1]
                ?? $this->conventions->foreignKeyColumnFromModel($name);
        }

        return new Column($name, $method);
    }
}
