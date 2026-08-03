<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\ValueObjects;

use Chr15k\SchemaAudit\Contracts\SchemaReference;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SourceLocation;
use Stringable;

final readonly class Finding implements Stringable
{
    /**
     * @param  list<SchemaReference>  $related
     */
    public function __construct(
        public string $code,
        public string $table,
        public string $message,
        public ?string $column = null,
        public Severity $severity = Severity::Warning,
        public ?SchemaGuard $guard = null,
        public ?SourceLocation $location = null,
        public array $related = [],
    ) {}

    public function __toString(): string
    {
        return str($this->code)->headline()->toString();
    }
}
