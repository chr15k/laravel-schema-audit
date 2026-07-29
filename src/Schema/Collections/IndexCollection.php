<?php

namespace Chr15k\SchemaAudit\Schema\Collections;

use Chr15k\SchemaAudit\Schema\ValueObjects\Index;
use Chr15k\SchemaAudit\Schema\ValueObjects\RedundantIndex;
use Illuminate\Support\Collection;

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

    public function duplicated(): static
    {
        $seen = [];

        return $this->filter(function (Index $index) use (&$seen): bool {
            $signature = $index->signature();

            if (isset($seen[$signature])) {
                return true;
            }

            $seen[$signature] = true;

            return false;
        });
    }

    /**
     * @return Collection<int, RedundantIndex>
     */
    public function redundant(): Collection
    {
        $redundant = collect();

        foreach ($this as $candidate) {
            foreach ($this as $other) {
                if (! $candidate->isCoveredBy($other)) {
                    continue;
                }

                $redundant->push(new RedundantIndex(
                    index: $candidate,
                    coveredBy: $other,
                ));

                break;
            }
        }

        return $redundant;
    }

    public function isCoveredBy(Index $other): bool
    {
        if ($this->name === $other->name) {
            return false;
        }

        if ($this->unique && ! $other->unique) {
            return false;
        }

        if (
            ! $this->unique &&
            $other->unique &&
            $this->columns === $other->columns
        ) {
            return true;
        }

        if (count($this->columns) >= count($other->columns)) {
            return false;
        }

        return $this->columns === array_slice(
            $other->columns,
            0,
            count($this->columns),
        );
    }
}
