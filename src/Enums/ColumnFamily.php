<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

enum ColumnFamily: string
{
    case BigUnsigned = 'big_unsigned';
    case BigSigned = 'big_signed';

    case IntUnsigned = 'int_unsigned';
    case IntSigned = 'int_signed';

    case MediumUnsigned = 'medium_unsigned';
    case MediumSigned = 'medium_signed';

    case SmallUnsigned = 'small_unsigned';
    case SmallSigned = 'small_signed';

    case TinyUnsigned = 'tiny_unsigned';
    case TinySigned = 'tiny_signed';

    case Uuid = 'uuid';
    case Ulid = 'ulid';

    case String = 'string';
    case Text = 'text';

    case Float = 'float';
    case Decimal = 'decimal';

    case Boolean = 'boolean';
    case Json = 'json';
    case DateTime = 'datetime';
    case Binary = 'binary';

    case Other = 'other';
}
