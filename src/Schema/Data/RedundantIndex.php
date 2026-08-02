<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Data;

use Chr15k\SchemaAudit\Schema\ValueObjects\Index;

final readonly class RedundantIndex
{
    public function __construct(
        public Index $index,
        public Index $coveredBy,
    ) {}
}
