<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Contracts\Rule;
use Chr15k\SchemaAudit\Data\Finding;

final readonly class SchemaAuditor
{
    /**
     * @param  list<Rule>  $rules
     */
    public function __construct(private iterable $rules) {}

    /**
     * @param  array<string, TableSchema>  $tables
     * @return list<Finding>
     */
    public function audit(array $tables): array
    {
        $findings = [];

        foreach ($this->rules as $rule) {
            foreach ($rule->check($tables) as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }
}
