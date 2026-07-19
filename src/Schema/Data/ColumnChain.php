<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Data;

/**
 * One full `$table->x()->y()->z()` statement, captured as an ordered list
 * of calls — the root call (directly on $table) first, then each chained
 * modifier in the order written. Chain-awareness is what lets us tell
 * apart e.g. $table->string('email')->unique() (a column-level modifier)
 * from $table->unique('a', 'b') (a table-level composite index) — both
 * produce an 'unique' ColumnCall, but only chain position disambiguates
 * what it means.
 */
final class ColumnChain
{
    /**
     * @param  list<ColumnCall>  $calls  root-first
     */
    public function __construct(
        public readonly array $calls,
    ) {}

    public function root(): ColumnCall
    {
        return $this->calls[0];
    }

    /** @return list<ColumnCall> everything after the root, in written order */
    public function modifiers(): array
    {
        return array_slice($this->calls, 1);
    }

    public function hasModifier(string $method): bool
    {
        foreach ($this->modifiers() as $call) {
            if ($call->method === $method) {
                return true;
            }
        }

        return false;
    }

    public function modifier(string $method): ?ColumnCall
    {
        foreach ($this->modifiers() as $call) {
            if ($call->method === $method) {
                return $call;
            }
        }

        return null;
    }
}
