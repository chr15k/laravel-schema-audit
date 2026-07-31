<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers\ValueObjects;

final readonly class ColumnCall
{
    /**
     * @param  array<int|string, string>  $arguments
     */
    public function __construct(
        public string $method,
        public array $arguments = []
    ) {}

    public function argument(int|string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }
}
