<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Contracts\Rule;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Chr15k\SchemaAudit\ValueObjects\Schema;

final readonly class SchemaAuditor
{
    /**
     * @param  list<Rule>  $rules
     */
    public function __construct(private array $rules) {}

    /**
     * @return list<Finding>
     */
    public function audit(Schema $schema): array
    {
        if ($this->rules === []) {
            return [];
        }

        return array_merge(...array_map(
            fn (Rule $rule): array => $rule->check($schema),
            $this->rules
        ));
    }

    public function ruleCount(): int
    {
        return count($this->rules);
    }
}
