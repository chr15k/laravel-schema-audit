<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Chr15k\SchemaAudit\Enums\ColumnFamily;
use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, string|int>
 */
final readonly class Column implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $name,
        public ColumnMethod $method
    ) {}

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
            'name'   => $this->name,
            'method' => $this->method->value,
        ];
    }
}
