<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Chr15k\SchemaAudit\Parsers\ValueObjects\SourceLocation;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, ?string>
 */
final readonly class ForeignKey implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $column,
        public string $referencesTable,
        public string $referencesColumn,
        public ?string $name = null,
        public ?SourceLocation $location = null
    ) {}

    public function withColumn(string $column): self
    {
        return new self(
            column: $column,
            referencesTable: $this->referencesTable,
            referencesColumn: $this->referencesColumn,
            name: $this->name,
            location: $this->location
        );
    }

    public function withReferencesTable(string $table): self
    {
        return new self(
            column: $this->column,
            referencesTable: $table,
            referencesColumn: $this->referencesColumn,
            name: $this->name,
            location: $this->location
        );
    }

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
            'column'            => $this->column,
            'references_table'  => $this->referencesTable,
            'references_column' => $this->referencesColumn,
            'name'              => $this->name,
            'location'          => $this->location,
        ];
    }
}
