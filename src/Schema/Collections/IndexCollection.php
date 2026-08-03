<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Collections;

use Chr15k\SchemaAudit\Schema\Data\RedundantIndex;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Index>
 */
final class IndexCollection extends Collection
{
    public function indexesColumn(string $column): bool
    {
        return $this->contains(
            fn (Index $index): bool => ($index->columns[0] ?? null) === $column
        );
    }

    /**
     * @param  list<string>  $columns
     */
    public function hasExactColumns(array $columns): bool
    {
        return $this->contains(
            fn (Index $index): bool => $index->columns === $columns
        );
    }

    public function withoutColumn(string $name): static
    {
        return $this->reject(
            fn (Index $index): bool => $index->columns === [$name]
        );
    }

    public function withoutName(string $name): static
    {
        return $this->reject(
            fn (Index $index): bool => $index->name === $name
        );
    }

    public function renameColumn(string $from, string $to): static
    {
        return $this->map(
            fn (Index $index): Index => $index->renameColumn($from, $to)
        );
    }

    /**
     * @return Collection<int, IndexCollection>
     */
    public function duplicateGroups(): Collection
    {
        return $this
            ->groupBy(
                fn (Index $index): string => $index->signature()
            )
            ->filter(
                fn (Collection $group): bool => $group->count() > 1
            )
            ->map(
                fn (Collection $group): IndexCollection => new self(
                    $group->values()->all()
                )
            )
            ->values();
    }

    /**
     * @return Collection<int, RedundantIndex>
     */
    public function redundant(): Collection
    {
        $redundant = collect();

        foreach ($this as $candidate) {
            foreach ($this as $other) {
                if ($candidate->isCoveredBy($other)) {
                    $redundant->push(new RedundantIndex(
                        index: $candidate,
                        coveredBy: $other,
                    ));

                    break;
                }
            }
        }

        return $redundant;
    }
}
