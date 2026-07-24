<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Closure;

final readonly class UnindexedForeignKeyRule extends Rule
{
    private const array AUTO_INDEXING_DRIVERS = ['mysql', 'mariadb'];

    public function __construct(private string $driver) {}

    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        if (in_array($this->driver, self::AUTO_INDEXING_DRIVERS, true)) {
            return $next($context);
        }

        $findings = [];

        foreach ($context->schema->tables() as $table) {
            foreach ($table->foreignKeys() as $fk) {
                if (! $table->isIndexed($fk->column)) {
                    $findings[] = $this->makeFinding(
                        table: $table->name,
                        message: sprintf("Foreign key '%s' has no covering index. %s does not auto-index foreign key columns; add an index to improve lookup and join performance.", $fk->column, $this->driver),
                        column: $fk->column,
                    );
                }
            }
        }

        $context = $context->withFindings($findings);

        return $next($context);
    }
}
