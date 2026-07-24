<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Closure;

final readonly class DuplicateForeignKeyRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            $seen = [];

            foreach ($table->foreignKeys() as $fk) {
                if (isset($seen[$fk->column])) {
                    $findings[] = $this->makeFinding(
                        table: $table->name,
                        message: sprintf("Duplicate foreign key on column '%s' - defined more than once. Remove the redundant constraint.", $fk->column),
                        column: $fk->column,
                    );

                    continue;
                }

                $seen[$fk->column] = true;
            }
        }

        $context = $context->withFindings($findings);

        return $next($context);
    }
}
