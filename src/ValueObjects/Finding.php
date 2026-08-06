<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\ValueObjects;

use Chr15k\SchemaAudit\Contracts\SchemaReference;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SourceLocation;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;

final readonly class Finding implements Arrayable, JsonSerializable, SchemaReference, Stringable
{
    /**
     * @param  array<int, SchemaReference>  $related
     * @param  string|list<string>|null  $columns
     */
    public function __construct(
        public string $code,
        public string $table,
        public string $message,
        public null|string|array $columns = null,
        public Severity $severity = Severity::Warning,
        public ?SchemaGuard $guard = null,
        public ?SourceLocation $location = null,
        public array $related = [],
    ) {}

    public function __toString(): string
    {
        return str($this->code)->headline()->toString();
    }

    public function location(): ?SourceLocation
    {
        return $this->location;
    }

    public function guard(): ?SchemaGuard
    {
        return $this->guard;
    }

    /**
     * @return list<Finding>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return [
            'code'     => $this->code,
            'table'    => $this->table,
            'message'  => strip_tags($this->message),
            'columns'  => $this->columns,
            'severity' => $this->severity->value,
            'guard'    => $this->guard?->value,
            'location' => $this->location,
            'related'  => $this->related,
        ];
    }
}
