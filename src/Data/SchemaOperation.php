<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Data;

/**
 * One Schema::create(...) or Schema::table(...) block as literally written
 * in a single migration file — not yet folded against other files.
 */
final readonly class SchemaOperation
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
        public string $type,
        public string $tableName,
        public array $chains = [],
        public ?string $renameTo = null,
    ) {}
}
