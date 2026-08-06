<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Closure;

final readonly class RedundantIndexRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            foreach ($table->indexes()->redundant() as $redundant) {
                $findings[] = $this->makeFinding(
                    table: $table->name,
                    message: sprintf(
                        'Index %s is covered by %s',
                        $redundant->index->name,
                        $redundant->coveredBy->name,
                    ),
                    columns: implode(', ', $redundant->index->columns),
                    guard: $table->guard(),
                    location: $redundant->index->location,
                    related: [$redundant->coveredBy]
                );
            }
        }

        return $next($context->withFindings($findings));
    }
}
