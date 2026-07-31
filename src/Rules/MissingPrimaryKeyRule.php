<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\Severity;
use Closure;

final readonly class MissingPrimaryKeyRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            if (! $table->hasPrimaryKey()) {
                $findings[] = $this->makeFinding(
                    table: $table->name,
                    message: sprintf('Table <fg=default>%s</> has no primary key', $table->name),
                    severity: Severity::Error,
                    conditional: $table->isConditionallyModified()
                );
            }
        }

        return $next($context->withFindings($findings));
    }
}
