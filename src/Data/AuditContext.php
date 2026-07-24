<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Data;

use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\SchemaAudit;
use Chr15k\SchemaAudit\ValueObjects\Finding;

final readonly class AuditContext
{
    public function __construct(
        public Schema $schema,
        public SchemaAudit $audit = new SchemaAudit
    ) {}

    /**
     * @param  list<Finding>  $findings
     */
    public function withFindings(array $findings): self
    {
        return new self(
            schema: $this->schema,
            audit: $this->audit->withFindings($findings),
        );
    }
}
