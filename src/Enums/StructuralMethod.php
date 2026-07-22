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
    case DropIndex = 'dropIndex';
    case DropUnique = 'dropUnique';
    case DropForeign = 'dropForeign';
    case Primary = 'primary';
}
