<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return mb_strtoupper($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Error   => 'red',
            self::Warning => 'yellow',
            self::Info    => 'blue',
        };
    }

    public function glyph(): string
    {
        return match ($this) {
            self::Error   => '✗',
            self::Warning => '▲',
            self::Info    => 'ℹ',
        };
    }

    public function colorTag(): string
    {
        return match ($this) {
            self::Error   => 'fg=white;bg=red',
            self::Warning => 'fg=black;bg=yellow',
            self::Info    => 'fg=white;bg=blue',
        };
    }
}
