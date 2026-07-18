<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

final class Index
{
    /**
     * @param  list<string>  $columns  ordered — order matters for composite-index matching
     */
    public function __construct(
        public readonly ?string $name,
        public readonly array $columns,
        public readonly bool $unique = false,
    ) {}
}
