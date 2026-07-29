<?php

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

    public function duplicated(): static
    {
        $seen = [];

        return $this->filter(function (ForeignKey $fk) use (&$seen): bool {
            if (isset($seen[$fk->column])) {
                return true;
            }

            $seen[$fk->column] = true;

            return false;
        });
    }
}
