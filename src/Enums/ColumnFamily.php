<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

enum ColumnFamily: string
{
    case Big = 'big';
    case Int = 'int';
    case Small = 'small';
    case Medium = 'medium';
    case Uuid = 'uuid';
    case Ulid = 'ulid';
}
