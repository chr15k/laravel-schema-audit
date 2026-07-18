<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

final class ForeignKey
{
    public function __construct(
        public readonly string $column,
        public readonly ?string $referencesTable,
    ) {}
}
