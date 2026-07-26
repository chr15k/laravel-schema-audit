<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\ValueObjects\Index;
use Closure;

final readonly class RedundantIndexRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->tables() as $table) {
            $indexes = $table->indexes();

            foreach ($indexes as $candidate) {
                foreach ($indexes as $other) {
                    if (! $this->isRedundant($candidate, $other)) {
                        continue;
                    }

                    $findings[] = $this->makeFinding(
                        table: $table->name,
                        message: sprintf(
                            "Index '%s' is redundant because it is covered by index '%s'. Consider removing it.",
                            $candidate->name,
                            $other->name,
                        ),
                        column: implode(', ', $candidate->columns),
                    );

                    break;
                }
            }
        }

        return $next($context->withFindings($findings));
    }

    private function isRedundant(Index $candidate, Index $other): bool
    {
        // Same index is handled by DuplicateIndexRule
        if ($candidate->name === $other->name) {
            return false;
        }

        // Non-unique index is redundant if an equivalent unique index exists
        if (
            ! $candidate->unique &&
            $other->unique &&
            $candidate->columns === $other->columns
        ) {
            return true;
        }

        // Unique index cannot be replaced by non-unique index
        if ($candidate->unique && ! $other->unique) {
            return false;
        }

        // Composite prefix check
        if (count($candidate->columns) >= count($other->columns)) {
            return false;
        }

        return $candidate->columns === array_slice(
            $other->columns,
            0,
            count($candidate->columns),
        );
    }
}
