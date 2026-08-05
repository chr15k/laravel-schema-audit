<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Chr15k\SchemaAudit\Contracts\SchemaReference;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SourceLocation;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, ?string>
 */
final readonly class ForeignKey implements Arrayable, JsonSerializable, SchemaReference
{
    /**
     * @param  string|list<string>  $columns
     * @param  string|list<string>  $referencesColumn
     */
    public function __construct(
        public string|array $columns,
        public string $referencesTable,
        public string|array $referencesColumn,
        public ?string $name = null,
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
            referencesTable: $this->referencesTable,
            referencesColumn: $this->referencesColumn,
            name: $this->name,
            location: $this->location,
            guard: $guard
        );
    }

    /**
     * @param  string|list<string>  $columns
     */
    public function withColumns(string|array $columns): self
    {
        return new self(
            columns: $columns,
            referencesTable: $this->referencesTable,
            referencesColumn: $this->referencesColumn,
            name: $this->name,
            location: $this->location,
            guard: $this->guard
        );
    }

    public function withReferencesTable(string $table): self
    {
        return new self(
            columns: $this->columns,
            referencesTable: $table,
            referencesColumn: $this->referencesColumn,
            name: $this->name,
            location: $this->location,
            guard: $this->guard
        );
    }

    public function signature(): string
    {
        return sprintf(
            '%s|%s|%s',
            is_array($this->columns)
                ? implode('_', $this->columns)
                : $this->columns,
            $this->referencesTable,
            is_array($this->referencesColumn)
                ? implode('_', $this->referencesColumn)
                : $this->referencesColumn
        );
    }

    /**
     * @return array{
     *     columns: string|list<string>,
     *     references_table: string,
     *     name: ?string,
     *     references_column: string|list<string>,
     *     location: ?SourceLocation
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{
     *     columns: string|list<string>,
     *     references_table: string,
     *     name: ?string,
     *     references_column: string|list<string>,
     *     location: ?SourceLocation
     * }
     */
    public function toArray(): array
    {
        return [
            'columns'           => $this->columns,
            'references_table'  => $this->referencesTable,
            'references_column' => $this->referencesColumn,
            'name'              => $this->name,
            'location'          => $this->location,
        ];
    }
}
