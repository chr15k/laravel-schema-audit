<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, array|bool|string|null>
 */
final readonly class Index implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public ?string $name,
        public array $columns,
        public bool $unique = false,
    ) {}

    public function renameColumn(string $from, string $to): self
    {
        return new self(
            name: $this->name,
            columns: array_map(
                fn (string $column): string => $column === $from ? $to : $column,
                $this->columns,
            ),
            unique: $this->unique,
        );
    }

    /**
     * Determines whether this index is made redundant by another index.
     */
    public function isCoveredBy(self $other): bool
    {
        if ($this->name === $other->name) {
            return false;
        }

        // A unique index cannot be replaced by a non-unique index.
        if ($this->unique && ! $other->unique) {
            return false;
        }

        // Equivalent columns, but unique index covers non-unique index.
        if (
            ! $this->unique &&
            $other->unique &&
            $this->columns === $other->columns
        ) {
            return true;
        }

        // Left-prefix rule.
        if (count($this->columns) >= count($other->columns)) {
            return false;
        }

        return $this->columns === array_slice(
            $other->columns,
            0,
            count($this->columns),
        );
    }

    /**
     * @return array{
     *     name: ?string,
     *     columns: list<string>,
     *     unique: bool,
     *     signature: string
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{
     *     name: ?string,
     *     columns: list<string>,
     *     unique: bool,
     *     signature: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name'      => $this->name,
            'columns'   => $this->columns,
            'unique'    => $this->unique,
            'signature' => $this->signature(),
        ];
    }

    public function signature(): string
    {
        return implode(',', $this->columns).'|'.($this->unique ? 'unique' : 'plain');
    }
}
