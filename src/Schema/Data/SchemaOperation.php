<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Data;

/**
 * One Schema::create(...) or Schema::table(...) block as literally written
 * in a single migration file — not yet folded against other files.
 */
final class SchemaOperation
{
    public const TYPE_CREATE = 'create';

    public const TYPE_ALTER = 'alter';

    public const TYPE_DROP = 'drop';

    public const TYPE_RENAME = 'rename';

    /**
     * @param  self::TYPE_*  $type
     * @param  list<ColumnChain>  $chains
     * @param  string|null  $renameTo  only set when type is TYPE_RENAME — the new table name
     */
    public function __construct(
        public readonly string $type,
        public readonly string $tableName,
        public readonly array $chains = [],
        public readonly ?string $renameTo = null,
    ) {}
}
