<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

enum SchemaGuard
{
    case HasTable;
    case MissingTable;

    case HasColumn;
    case MissingColumn;

    case Unknown;

    public function impliesConditional(): bool
    {
        return match ($this) {
            self::HasTable, self::HasColumn                        => false,
            self::MissingTable, self::MissingColumn, self::Unknown => true,
        };
    }
}
