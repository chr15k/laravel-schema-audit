<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Contracts;

use Chr15k\SchemaAudit\ValueObjects\Finding;
use Chr15k\SchemaAudit\ValueObjects\Schema;

/**
 * A single schema-level check. Deliberately takes the WHOLE folded schema
 * (not just one table) since some checks — dangling foreign keys — need
 * to look a table up by name to verify it exists.
 */
interface Rule
{
    /**
     * @return list<Finding>
     */
    public function check(Schema $schema): array;
}
