<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\ValueObjects;

final readonly class Index
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public ?string $name,
        public array $columns,
        public bool $unique = false,
    ) {}

    public function key(): string
    {
        return implode(',', $this->columns).'|'.($this->unique ? 'unique' : 'plain');
    }
}
