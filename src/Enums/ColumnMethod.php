<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

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
    case ForeignUuidFor = 'foreignUuidFor';

    public function requiresModel(): bool
    {
        return match ($this) {
            self::ForeignIdFor,
            self::ForeignUuidFor => true,
            default              => false,
        };
    }

    public function isForeignIdType(): bool
    {
        return match ($this) {
            self::ForeignId,
            self::ForeignUuid,
            self::ForeignUlid,
            self::ForeignIdFor,
            self::ForeignUuidFor => true,
            default              => false,
        };
    }

    public function impliesPrimaryKey(): bool
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

    public function family(): ColumnFamily
    {
        return match ($this) {
            // Unsigned Big Integer (e.g., $table->id(), $table->foreignId())
            self::Id,
            self::BigIncrements,
            self::ForeignId,
            self::ForeignIdFor,
            self::UnsignedBigInteger => ColumnFamily::BigUnsigned,

            // Signed Big Integer
            self::BigInteger => ColumnFamily::BigSigned,

            // Unsigned Integer
            self::Increments,
            self::UnsignedInteger => ColumnFamily::IntUnsigned,

            // Signed Integer (e.g., $table->integer())
            self::Integer => ColumnFamily::IntSigned,

            // Unsigned Medium Integer
            self::MediumIncrements,
            self::UnsignedMediumInteger => ColumnFamily::MediumUnsigned,

            // Signed Medium Integer
            self::MediumInteger => ColumnFamily::MediumSigned,

            // Unsigned Small Integer
            self::SmallIncrements,
            self::UnsignedSmallInteger => ColumnFamily::SmallUnsigned,

            // Signed Small Integer
            self::SmallInteger => ColumnFamily::SmallSigned,

            // Unsigned Tiny Integer
            self::UnsignedTinyInteger => ColumnFamily::TinyUnsigned,

            // Signed Tiny Integer
            self::TinyInteger => ColumnFamily::TinySigned,

            // UUID
            self::Uuid,
            self::ForeignUuid,
            self::ForeignUuidFor,
            self::UuidMorphs => ColumnFamily::Uuid,

            // ULID
            self::Ulid,
            self::ForeignUlid,
            self::UlidMorphs => ColumnFamily::Ulid,

            // String & Text
            self::String,
            self::Char,
            self::IpAddress,
            self::MacAddress => ColumnFamily::String,

            self::Text,
            self::TinyText,
            self::MediumText,
            self::LongText => ColumnFamily::Text,

            // Numbers
            self::Float,
            self::Double => ColumnFamily::Float,
            self::Decimal,
            self::UnsignedDecimal => ColumnFamily::Decimal,

            // Boolean, JSON, Dates
            self::Boolean => ColumnFamily::Boolean,
            self::Json,
            self::Jsonb => ColumnFamily::Json,

            self::Date,
            self::DateTime,
            self::DateTimeTz,
            self::Time,
            self::TimeTz,
            self::Timestamp,
            self::TimestampTz,
            self::SoftDeletes,
            self::SoftDeletesTz,
            self::Year => ColumnFamily::DateTime,

            self::Binary => ColumnFamily::Binary,

            // Spatial, Enums, Morphs, or unrecognized fallback
            default => ColumnFamily::Other,
        };
    }
}
