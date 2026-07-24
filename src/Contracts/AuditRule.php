<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Contracts;

use Chr15k\SchemaAudit\Data\AuditContext;
use Closure;

interface AuditRule
{
    /**
     * @param  Closure(AuditContext): AuditContext  $next
     */
    public function handle(AuditContext $context, Closure $next): AuditContext;
}
