<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

enum SchemaGuard
{
    case HasTable;
    case MissingTable;

    case HasColumn;
    case MissingColumn;
}
