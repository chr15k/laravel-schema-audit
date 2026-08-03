<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Chr15k\SchemaAudit\Contracts\SchemaReference;
use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SourceLocation;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, string|int>
 */
final readonly class Column implements Arrayable, JsonSerializable, SchemaReference
{
    public function __construct(
        public string $name,
        public ColumnMethod $method,
        public ?SourceLocation $location = null,
        public ?SchemaGuard $guard = null
    ) {}

    public function location(): ?SourceLocation
    {
        return $this->location;
    }

    public function guard(): ?SchemaGuard
    {
        return $this->guard;
    }

    public function withGuard(?SchemaGuard $guard = null): self
    {
        return new self(
            name: $this->name,
            method: $this->method,
            location: $this->location,
            guard: $guard
        );
    }

    /**
     * @return array{name: string, method: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{name: string, method: string}
     */
    public function toArray(): array
    {
        return [
            'name'     => $this->name,
            'method'   => $this->method->value,
            'location' => $this->location,
        ];
    }
}
