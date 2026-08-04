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
 * Deliberately conservative: only fires when BOTH sides resolve with
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

            $family = $mismatch->table->column($fk->columns)?->method->family()->value;
            $referencesFamily = $mismatch->referencedTable->column($fk->referencesColumn)?->method->family()->value;

            $findings[] = $this->makeFinding(
                table: $mismatch->table->name,
                message: sprintf(
                    'Type <fg=default>%s</> does not match %s.%s <fg=default>(%s)</>',
                    $family ?: '?',
                    $mismatch->referencedTable->name,
                    $fk->referencesColumn,
                    $referencesFamily ?: '?'
                ),
                column: $fk->columns,
                severity: Severity::Error,
                guard: $mismatch->foreignKey->guard,
                location: $mismatch->foreignKey->location
            );
        }

        return $next($context->withFindings($findings));
    }
}
