<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Resolvers;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Parsers\ValueObjects\ColumnChain;
use Chr15k\SchemaAudit\Schema\LaravelConventions;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;

final readonly class ColumnResolver
{
    public function __construct(
        private LaravelConventions $conventions,
    ) {}

    public function resolve(
        ColumnChain $chain,
        ?SchemaGuard $guard = null
    ): ?Column {
        $call = $chain->root();
        $method = ColumnMethod::tryFrom($call->method);

        if ($method === null) {
            return null;
        }

        $method = $this->resolveModifiers($method, $chain);

        $impliedPrimaryKey = $method->impliesPrimaryKey();

        $name = $call->argument(0) ?? ($impliedPrimaryKey ? 'id' : null);

        if ($name === null) {
            return null;
        }

        if ($method->isForeignIdType() && str_contains($name, '::class')) {
            $name = $call->argument(1)
                ?? $this->conventions->foreignKeyColumnFromModel($name);
        }

        return new Column(
            name: $name,
            method: $method,
            location: $call->location,
            conditional: $guard === SchemaGuard::Unknown
        );
    }

    private function resolveModifiers(ColumnMethod $method, ColumnChain $chain): ColumnMethod
    {
        if (! $this->isUnsigned($method, $chain)) {
            return $method;
        }

        return match ($method) {
            ColumnMethod::Integer       => ColumnMethod::UnsignedInteger,
            ColumnMethod::BigInteger    => ColumnMethod::UnsignedBigInteger,
            ColumnMethod::MediumInteger => ColumnMethod::UnsignedMediumInteger,
            ColumnMethod::SmallInteger  => ColumnMethod::UnsignedSmallInteger,
            ColumnMethod::TinyInteger   => ColumnMethod::UnsignedTinyInteger,
            default                     => $method,
        };
    }

    private function isUnsigned(ColumnMethod $method, ColumnChain $chain): bool
    {
        if (! $method->unsignable()) {
            return false;
        }

        if ($chain->hasModifier('unsigned')) {
            return true;
        }

        $call = $chain->root();

        // @todo - check this as I'm sure we typed arguments[] value as string...
        return $call->argument('unsigned', $call->argument(2)) === true;
    }
}
