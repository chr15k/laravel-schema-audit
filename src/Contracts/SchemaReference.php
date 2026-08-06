<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Contracts;

use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\ValueObjects\SourceLocation;

interface SchemaReference
{
    public function location(): ?SourceLocation;

    public function guard(): ?SchemaGuard;
}
