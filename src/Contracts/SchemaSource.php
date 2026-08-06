<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Contracts;

use Chr15k\SchemaAudit\Parsers\ValueObjects\SchemaOperation;

interface SchemaSource
{
    /**
     * @return iterable<SchemaOperation>
     */
    public function operations(): iterable;
}
