<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Chr15k\SchemaAudit\Contracts\SchemaReference;
use Chr15k\SchemaAudit\Schema\Collections\IndexCollection;
use LogicException;

/**
 * Represents a collection of duplicate indexes.
 */
final readonly class DuplicateIndexGroup
{
    public function __construct(
        public IndexCollection $indexes,
    ) {
        if ($this->indexes->isEmpty()) {
            throw new LogicException('A duplicate index group cannot be empty.');
        }
    }

    public function primary(): Index
    {
        /** @var Index $first */
        $first = $this->indexes->first();

        return $this->indexes->first(
            callback: fn (Index $index): bool => $index->guard()?->impliesConditional() === true,
            default: $first,
        );
    }

    /**
     * @return array<int, SchemaReference>
     */
    public function related(): array
    {
        return $this->indexes
            ->reject(fn (Index $index): bool => $index === $this->primary())
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function columns(): array
    {
        return $this->primary()->columns;
    }
}
