<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Enums\Severity;
use Closure;

/**
 * Flags a foreign key whose column type doesn't match the type family of
 * the referenced table's primary key — e.g. a foreignId() (unsigned big
 * integer) pointing at a table whose PK is a plain increments() (unsigned
 * integer). MySQL frequently rejects this outright at migrate time;
 * SQLite/loosely-enforced setups can let it exist silently.
 *
 * The approach is deliberately conservative: only fires when BOTH sides resolve with
 * confidence — the referenced table exists (DanglingForeignKeyRule
 * covers the case where it doesn't), and its primary key type is
 * resolvable via the auto-increment convention. A table using an
 * explicit $table->primary(...) call is silently skipped rather than
 * guessed at, since a wrong guess here produces a false mismatch, which
 * is worse than a missed one.
 */
final readonly class MismatchedForeignKeyRule extends Rule
{
    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        $findings = [];

        foreach ($context->schema->mismatchedForeignKeys() as $mismatch) {
            $fk = $mismatch->foreignKey;

            // Composite foreign keys require comparing each column pair.
            // Skip until composite type matching is supported.
            if (! is_string($fk->columns)) {
                continue;
            }

            if (! is_string($fk->referencesColumn)) {
                continue;
            }

            $column = $mismatch->table->column($fk->columns);
            $family = $column?->method->family()->value;

            $referencesColumn = $mismatch->referencedTable->column($fk->referencesColumn);
            $referencesFamily = $referencesColumn?->method->family()->value;

            $findings[] = $this->makeFinding(
                table: $mismatch->table->name,
                message: sprintf(
                    'Foreign key <fg=default>%s.%s</> type (%s) does not match referenced column <fg=default>%s.%s</> type (%s)',
                    $mismatch->table->name,
                    $fk->columns,
                    $family ?? '?',
                    $mismatch->referencedTable->name,
                    $fk->referencesColumn,
                    $referencesFamily ?? '?',
                ),
                columns: $fk->columns,
                severity: Severity::Error,
                guard: $fk->guard,
                location: $fk->location,
            );
        }

        return $next($context->withFindings($findings));
    }
}
