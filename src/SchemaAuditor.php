<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Contracts\Rule;
use Chr15k\SchemaAudit\Data\Finding;

/**
 * Runs a set of Rules against the folded schema and collects every
 * Finding. The default rule set is deliberately schema-only (no query
 * usage scanning) — see the package README for why this package is
 * scoped to migration-only analysis.
 */
final readonly class SchemaAuditor
{
    /**
     * @param  list<Rule>  $rules
     */
    public function __construct(private array $rules) {}

    public static function withDefaultRules(string $driver): self
    {
        return new self([
            new Rules\UnindexedForeignKeyRule($driver),
            new Rules\DuplicateIndexRule,
            new Rules\RedundantSingleColumnIndexRule,
            new Rules\DanglingForeignKeyRule,
            new Rules\NoPrimaryKeyRule,
        ]);
    }

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
