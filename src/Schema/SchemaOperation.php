<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

/**
 * One Schema::create(...) or Schema::table(...) block as literally written
 * in a single migration file — not yet folded against other files.
 */
final class SchemaOperation
{
    public const TYPE_CREATE = 'create';

    public const TYPE_ALTER = 'alter';

    /**
     * @param  self::TYPE_*  $type
     * @param  list<ColumnCall>  $columnCalls
     */
    public function __construct(
        public readonly string $type,
        public readonly string $tableName,
        public readonly array $columnCalls,
    ) {}
}
