<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Contracts;

use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\ValueObjects\Finding;

interface Rule
{
    /**
     * @return list<Finding>
     */
    public function check(Schema $schema): array;
}
