<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Chr15k\SchemaAudit\Schema\Collections\ForeignKeyCollection;
use LogicException;

/**
 * Represents a collection of duplicate FKs.
 */
final readonly class DuplicateForeignKeyGroup
{
    public function __construct(
        public ForeignKeyCollection $fks,
    ) {
        if ($this->fks->isEmpty()) {
            throw new LogicException('A duplicate foreign key group cannot be empty.');
        }
    }

    public function primary(): ForeignKey
    {
        /** @var ForeignKey $first */
        $first = $this->fks->first();

        return $this->fks->first(
            callback: fn (ForeignKey $fk): bool => $fk->guard?->impliesConditional() === true,
            default: $first,
        );
    }

    /**
     * @return array<int, ForeignKey>
     */
    public function related(): array
    {
        return $this->fks
            ->reject(fn (ForeignKey $fk): bool => $fk === $this->primary())
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function columns(): array
    {
        return (array) $this->primary()->columns;
    }
}
