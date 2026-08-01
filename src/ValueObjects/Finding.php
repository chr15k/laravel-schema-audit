<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\ValueObjects;

use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SourceLocation;
use Stringable;

final readonly class Finding implements Stringable
{
    public function __construct(
        public string $code,
        public string $table,
        public string $message,
        public ?string $column = null,
        public Severity $severity = Severity::Warning,
        public bool $conditional = false,
        public ?SourceLocation $location = null,
        /** @var array<string, SourceLocation> */
        public array $related = [],
    ) {}

    public function __toString(): string
    {
        return str($this->code)->headline()->toString();
    }
}
