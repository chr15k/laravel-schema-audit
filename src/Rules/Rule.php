<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Concerns\CreatesFindings;
use Chr15k\SchemaAudit\Contracts\AuditRule;

abstract readonly class Rule implements AuditRule
{
    use CreatesFindings;
}
