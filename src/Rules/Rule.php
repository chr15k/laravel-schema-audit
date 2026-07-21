<?php

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Concerns\CreatesFindings;
use Chr15k\SchemaAudit\Contracts\Rule as RuleContract;

abstract readonly class Rule implements RuleContract
{
    use CreatesFindings;
}
