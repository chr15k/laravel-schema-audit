<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers\ValueObjects;

final readonly class ColumnCall
{
    /**
     * @var list<string|list<string>|int|bool|null>
     */
    public function __construct(
        public string $method,
        public array $arguments = [],
        public ?SourceLocation $location = null
    ) {}

    /**
     * @template T
     *
     * @param  T  $default
     * @return string|list<string>|T
     */
    public function argument(int|string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }
}
