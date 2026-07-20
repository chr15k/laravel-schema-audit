<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

enum SchemaOperationType: string
{
    case Create = 'create';
    case Alter = 'alter';
    case Drop = 'drop';
    case Rename = 'rename';
}
