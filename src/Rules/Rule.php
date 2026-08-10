<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Concerns\CreatesFindings;
use Chr15k\SchemaAudit\Contracts\AuditRule;
use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Closure;

abstract readonly class Rule implements AuditRule
{
    use CreatesFindings;

    /**
     * @return iterable<Finding>
     */
    abstract protected function check(AuditContext $context): iterable;

    final public function handle(
        AuditContext $context,
        Closure $next,
    ): AuditContext {
        return $next(
            $context->withFindings(
                iterator_to_array($this->check($context))
            )
        );
    }
}
