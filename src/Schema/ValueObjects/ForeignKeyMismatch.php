<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Chr15k\SchemaAudit\Schema\TableSchema;

final readonly class ForeignKeyMismatch
{
    public function __construct(
        public TableSchema $table,
        public ForeignKey $foreignKey,
        public TableSchema $referencedTable,
    ) {}
}
