<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers\ValueObjects;

final readonly class SourceLocation
{
    public function __construct(
        public string $filename,
        public int $line,
    ) {}
}