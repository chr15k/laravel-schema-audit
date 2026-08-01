<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers\ValueObjects;

use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Enums\SchemaOperationType;

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
        public ?SchemaGuard $guard = null,
        public ?SourceLocation $location = null,
    ) {}
}
