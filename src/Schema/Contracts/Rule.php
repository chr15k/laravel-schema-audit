<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Contracts;

use Chr15k\SchemaAudit\Schema\Rules\Finding;
use Chr15k\SchemaAudit\Schema\TableSchema;

/**
 * A single schema-level check. Deliberately takes the WHOLE folded schema
 * (not just one table) since some checks — dangling foreign keys — need
 * to look a table up by name to verify it exists.
 */
interface Rule
{
    /**
     * @param  array<string, TableSchema>  $tables  table name => folded schema
     * @return list<Finding>
     */
    public function check(array $tables): array;
}
