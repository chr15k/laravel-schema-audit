<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\Severity;
use Closure;

final readonly class NoPrimaryKeyRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            if (! $table->hasPrimaryKey()) {
                $findings[] = $this->makeFinding(
                    table: $table->name,
                    message: 'No primary key found. Add an auto-incrementing id() or define an explicit primary(...) for this table.',
                    severity: Severity::Error,
                );
            }
        }

        $context = $context->withFindings($findings);

        return $next($context);
    }
}
