<?php

namespace Chr15k\SchemaAudit\Schema\Collections;

use Chr15k\SchemaAudit\Enums\ColumnMethod;
use Chr15k\SchemaAudit\Schema\ValueObjects\Column;
use Illuminate\Support\Collection;

/**
 * @extends Collection<string, Column>
 */
final class ColumnCollection extends Collection
{
    public function primaryKeyColumnMethod(): ?ColumnMethod
    {
        return $this->first(
            fn (Column $column): bool => $column->method->impliesPrimaryKey()
        )?->method;
    }

    public function rename(string $from, string $to): void
    {
        if (! $column = $this->get($from)) {
            return;
        }

        $this->put($to, $column);
        $this->forget($from);
    }
}
