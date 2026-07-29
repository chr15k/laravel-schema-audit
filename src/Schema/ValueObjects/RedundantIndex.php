<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

final readonly class RedundantIndex
{
    public function __construct(
        public Index $index,
        public Index $coveredBy,
    ) {}
}
