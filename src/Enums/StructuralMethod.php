<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

enum StructuralMethod: string
{
    case Primary = 'primary';
    case Foreign = 'foreign';
    case Unique = 'unique';
    case Index = 'index';
    case FullText = 'fullText';
    case DropColumn = 'dropColumn';
    case RenameColumn = 'renameColumn';
    case RenameIndex = 'renameIndex';
    case DropIndex = 'dropIndex';
    case DropUnique = 'dropUnique';
    case DropForeign = 'dropForeign';
    case DropFullText = 'dropFullText';
    case DropPrimary = 'dropPrimary';
    case DropSpatialIndex = 'dropSpatialIndex';
    case DropConstrainedForeignId = 'dropConstrainedForeignId';
    case DropForeignIdFor = 'dropForeignIdFor';
    case DropConstrainedForeignIdFor = 'dropConstrainedForeignIdFor';
    case DropTimestamps = 'dropTimestamps';
    case DropTimestampsTz = 'dropTimestampsTz';
    case DropRememberToken = 'dropRememberToken';
    case DropSoftDeletes = 'dropSoftDeletes';

    public function isDestructive(): bool
    {
        return match ($this) {
            self::DropForeign,
            self::DropIndex,
            self::DropFullText,
            self::DropUnique,
            self::DropPrimary,
            self::DropSpatialIndex,
            self::DropConstrainedForeignId,
            self::DropForeignIdFor,
            self::DropConstrainedForeignIdFor,
            self::DropTimestamps,
            self::DropTimestampsTz,
            self::DropRememberToken,
            self::DropSoftDeletes,
            self::DropColumn => true,
            default          => false,
        };
    }
}
