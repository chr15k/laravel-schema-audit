<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Rules;

use Chr15k\SchemaAudit\Schema\TableSchema;

/**
 * Runs a set of Rules against the folded schema and collects every
 * Finding. The default rule set is deliberately schema-only (no query
 * usage scanning) — see the package README for why this package is
 * scoped to migration-only analysis.
 */
final class SchemaAuditor
{
    /**
     * @param  list<Rule>  $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {}

    public static function withDefaultRules(string $driver): self
    {
        return new self([
            new UnindexedForeignKeyRule($driver),
            new DuplicateIndexRule,
            new RedundantSingleColumnIndexRule,
            new DanglingForeignKeyRule,
            new NoPrimaryKeyRule,
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
