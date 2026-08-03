<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers\ValueObjects;

final readonly class ColumnCall
{
    /**
     * @param  array<int|string, array|string>  $arguments
     */
    public function __construct(
        public string $method,
        public array $arguments = [],
        public ?SourceLocation $location = null
    ) {}

    public function argument(int|string $key, null|array|string $default = null): null|array|string
    {
        return $this->arguments[$key] ?? $default;
    }
}
