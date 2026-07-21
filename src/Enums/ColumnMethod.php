<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

/**
 * Every Blueprint method that defines a column, in the whitelist
 * SchemaBuilder uses to decide "is this call a column definition" — see
 * SchemaBuilder's docblock for why this is a whitelist rather than a
 * blacklist of "structural" methods.
 *
 * Case values match the literal Blueprint method name, so a parsed
 * method name string can be looked up directly via tryFrom().
 */
enum ColumnMethod: string
{
    case Id = 'id';
    case Increments = 'increments';
    case BigIncrements = 'bigIncrements';
    case SmallIncrements = 'smallIncrements';
    case MediumIncrements = 'mediumIncrements';

    case Integer = 'integer';
    case TinyInteger = 'tinyInteger';
    case SmallInteger = 'smallInteger';
    case MediumInteger = 'mediumInteger';
    case BigInteger = 'bigInteger';
    case UnsignedInteger = 'unsignedInteger';
    case UnsignedTinyInteger = 'unsignedTinyInteger';
    case UnsignedSmallInteger = 'unsignedSmallInteger';
    case UnsignedMediumInteger = 'unsignedMediumInteger';
    case UnsignedBigInteger = 'unsignedBigInteger';

    case Float = 'float';
    case Double = 'double';
    case Decimal = 'decimal';
    case UnsignedDecimal = 'unsignedDecimal';

    case String = 'string';
    case Char = 'char';
    case Text = 'text';
    case TinyText = 'tinyText';
    case MediumText = 'mediumText';
    case LongText = 'longText';

    case Boolean = 'boolean';
    case Enum = 'enum';
    case Set = 'set';
    case Json = 'json';
    case Jsonb = 'jsonb';

    case Date = 'date';
    case DateTime = 'dateTime';
    case DateTimeTz = 'dateTimeTz';
    case Time = 'time';
    case TimeTz = 'timeTz';
    case Timestamp = 'timestamp';
    case TimestampTz = 'timestampTz';
    case SoftDeletes = 'softDeletes';
    case SoftDeletesTz = 'softDeletesTz';
    case Year = 'year';

    case Binary = 'binary';
    case Uuid = 'uuid';
    case Ulid = 'ulid';
    case IpAddress = 'ipAddress';
    case MacAddress = 'macAddress';

    case Geometry = 'geometry';
    case Geography = 'geography';
    case Point = 'point';
    case LineString = 'lineString';
    case Polygon = 'polygon';

    case Morphs = 'morphs';
    case NullableMorphs = 'nullableMorphs';
    case UuidMorphs = 'uuidMorphs';
    case UlidMorphs = 'ulidMorphs';

    case ForeignId = 'foreignId';
    case ForeignUuid = 'foreignUuid';
    case ForeignUlid = 'foreignUlid';
    case ForeignIdFor = 'foreignIdFor';

    /**
     * True for the auto-incrementing id-style methods that imply a
     * primary key by Laravel convention, AND that default to a column
     * named 'id' when called with no explicit name argument (e.g.
     * $table->id() with no args). Both facts happen to apply to exactly
     * the same set of methods, so this is deliberately one predicate
     * rather than two — SchemaBuilder uses it to resolve the default
     * column name, TableSchema uses it to detect a primary key.
     */
    public function impliesAutoIncrementingPrimaryKey(): bool
    {
        return match ($this) {
            self::Id,
            self::Increments,
            self::BigIncrements,
            self::SmallIncrements,
            self::MediumIncrements => true,
            default                => false,
        };
    }

    public function toType(): string
    {
        $type = match ($this) {
            self::Id               => self::UnsignedBigInteger,
            self::Increments       => self::UnsignedInteger,
            self::BigIncrements    => self::UnsignedBigInteger,
            self::SmallIncrements  => self::UnsignedSmallInteger,
            self::MediumIncrements => self::UnsignedMediumInteger,
            default                => $this,
        };

        return $type->value;
    }
}
