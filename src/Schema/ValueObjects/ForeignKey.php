<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, ?string>
 */
final readonly class ForeignKey implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $column,
        public ?string $referencesTable,
        public ?string $name = null,
    ) {}

    /**
     * @return array{column: string, references_table: ?string, name: ?string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{column: string, references_table: ?string, name: ?string}
     */
    public function toArray(): array
    {
        return [
            'column'           => $this->column,
            'references_table' => $this->referencesTable,
            'name'             => $this->name,
        ];
    }
}
