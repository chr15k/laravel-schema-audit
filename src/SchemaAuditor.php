<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Contracts\Rule;
use Chr15k\SchemaAudit\Schema\Schema;

final readonly class SchemaAuditor
{
    /**
     * @param  list<Rule>  $rules
     */
    public function __construct(private array $rules) {}

    public function audit(Schema $schema): SchemaAudit
    {
        if ($this->rules === []) {
            return new SchemaAudit([]);
        }

        $findings = array_merge(...array_map(
            fn (Rule $rule): array => $rule->check($schema),
            $this->rules
        ));

        return new SchemaAudit($findings);
    }

    public function ruleCount(): int
    {
        return count($this->rules);
    }
}
