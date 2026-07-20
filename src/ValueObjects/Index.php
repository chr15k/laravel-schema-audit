<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\ValueObjects;

final readonly class Index
{
    /**
     * @param  list<string>  $columns  ordered — order matters for composite-index matching
     */
    public function __construct(
        public ?string $name,
        public array $columns,
        public bool $unique = false,
    ) {}
}
