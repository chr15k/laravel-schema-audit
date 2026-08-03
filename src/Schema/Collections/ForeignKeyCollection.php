<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Collections;

use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, ForeignKey>
 */
final class ForeignKeyCollection extends Collection
{
    public function withoutName(string $name): static
    {
        return $this->reject(
            fn (ForeignKey $fk): bool => $fk->name === $name
        );
    }

    public function renameColumn(string $from, string $to): static
    {
        return $this->map(
            fn (ForeignKey $fk): ForeignKey => $fk->column === $from
                ? $fk->withColumn($to)
                : $fk
        );
    }

    public function renameReferencedTable(string $from, string $to): static
    {
        return $this->map(
            fn (ForeignKey $fk): ForeignKey => $fk->referencesTable === $from
                ? $fk->withReferencesTable($to)
                : $fk
        );
    }

    /**
     * @return Collection<int, ForeignKeyCollection>
     */
    public function duplicateGroups(): Collection
    {
        return $this
            ->groupBy(
                fn (ForeignKey $fk): string => $fk->signature()
            )
            ->filter(
                fn (Collection $group): bool => $group->count() > 1
            )
            ->map(
                fn (Collection $group): ForeignKeyCollection => new self(
                    $group->values()->all()
                )
            )
            ->values();
    }
}
