<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\ValueObjects;

use Chr15k\SchemaAudit\Enums\Severity;
use Stringable;

final readonly class Finding implements Stringable
{
    public function __construct(
        public string $code,
        public string $table,
        public string $message,
        public ?string $column = null,
        public Severity $severity = Severity::Warning,
    ) {}

    public function __toString(): string
    {
        return str($this->code)->headline()->toString();
    }
}
