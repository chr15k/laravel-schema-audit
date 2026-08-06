<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Chr15k\SchemaAudit\Contracts\SchemaReference;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\ValueObjects\SourceLocation;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, array|bool|string|null>
 */
final readonly class PrimaryKey implements Arrayable, JsonSerializable, SchemaReference
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public array $columns,
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
            columns: $this->columns,
            location: $this->location,
            guard: $guard
        );
    }

    /**
     * @return array{columns: list<string>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{columns: list<string>}
     */
    public function toArray(): array
    {
        return [
            'columns'  => $this->columns,
            'location' => $this->location,
        ];
    }
}
