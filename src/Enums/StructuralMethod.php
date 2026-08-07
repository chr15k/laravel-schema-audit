<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Enums;

enum StructuralMethod: string
{
    case Foreign = 'foreign';
    case Unique = 'unique';
    case Index = 'index';
    case FullText = 'fullText';
    case DropColumn = 'dropColumn';
    case RenameColumn = 'renameColumn';
    case RenameIndex = 'renameIndex'; // @todo
    case DropIndex = 'dropIndex';
    case DropUnique = 'dropUnique';
    case DropForeign = 'dropForeign';
    case DropFullText = 'dropFullText';
    case DropPrimary = 'dropPrimary';
    case DropSpatialIndex = 'dropSpatialIndex';
    case DropConstrainedForeignId = 'dropConstrainedForeignId';
    case DropForeignIdFor = 'dropForeignIdFor';
    case DropConstrainedForeignIdFor = 'dropConstrainedForeignIdFor';
    case Primary = 'primary';

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
            self::DropColumn => true,
            default          => false,
        };
    }
}
