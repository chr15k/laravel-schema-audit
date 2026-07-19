<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Data;

final readonly class ForeignKey
{
    public function __construct(
        public string $column,
        public ?string $referencesTable,
        public ?string $name = null,
    ) {}
}
