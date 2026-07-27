<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Closure;

final readonly class MissingReferencedKeyRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        // @todo - covers this scenario:
        // "General error: 6125 Failed to add the foreign key constraint. Missing unique key for constraint ..."
        return $next($context);
    }
}
