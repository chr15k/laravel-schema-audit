<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Closure;

final readonly class DuplicateIndexRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            $seen = [];

            foreach ($table->indexes() as $index) {
                if (isset($seen[$index->signature()])) {
                    $findings[] = $this->makeFinding(
                        table: $table->name,
                        message: sprintf("Duplicate index on columns '%s' - declared more than once. Remove the redundant index.", implode(', ', $index->columns)),
                        column: implode(',', $index->columns),
                    );

                    continue;
                }

                $seen[$index->signature()] = true;
            }
        }

        return $next($context->withFindings($findings));
    }
}
