<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\ValueObjects;

use Chr15k\SchemaAudit\Enums\SchemaOperationType;

/**
 * One Schema::create(...) or Schema::table(...) block as literally written
 * in a single migration file — not yet folded against other files.
 */
final readonly class SchemaOperation
{
    /**
     * @param  list<ColumnChain>  $chains
     */
    public function __construct(
        public SchemaOperationType $type,
        public string $tableName,
        public array $chains = [],
        public ?string $renameTo = null,
    ) {}
}
