<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\ValueObjects;

final readonly class ColumnChain
{
    /**
     * @param  list<ColumnCall>  $calls  root-first
     */
    public function __construct(
        public array $calls,
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
