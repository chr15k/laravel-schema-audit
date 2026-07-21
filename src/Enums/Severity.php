<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

/**
 * How serious a Finding is. ERROR = a real correctness bug (dangling
 * reference, type mismatch, missing primary key). WARNING = dead weight
 * or a likely-but-not-certain problem (duplicate index, missing index).
 * Nothing currently maps to INFO — kept as a level for custom rules that
 * want a purely advisory tier, not because a built-in rule needs it.
 */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    /**
     * Symfony Console table cells support inline <fg=...;bg=...> tags —
     * this returns the tag pair to wrap a cell value in for this severity.
     */
    public function colorTag(): string
    {
        return match ($this) {
            self::Error => 'fg=white;bg=red',
            self::Warning => 'fg=black;bg=yellow',
            self::Info => 'fg=white;bg=blue',
        };
    }
}
