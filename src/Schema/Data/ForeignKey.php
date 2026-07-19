<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Data;

final class ForeignKey
{
    public function __construct(
        public readonly string $column,
        public readonly ?string $referencesTable,
        public readonly ?string $name = null,
    ) {}
}
