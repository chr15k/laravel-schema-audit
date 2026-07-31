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
                        'Index <fg=default>%s</> is covered by <fg=default>%s</>',
                        $redundant->index->name,
                        $redundant->coveredBy->name,
                    ),
                    column: implode(', ', $redundant->index->columns),
                    conditional: $table->isConditionallyModified()
                );
            }
        }

        return $next($context->withFindings($findings));
    }
}
