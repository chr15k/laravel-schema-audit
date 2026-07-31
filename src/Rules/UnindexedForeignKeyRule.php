<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Support\Config;
use Closure;

final readonly class UnindexedForeignKeyRule extends Rule
{
    private const array AUTO_INDEXING_DRIVERS = ['mysql', 'mariadb'];

    public function __construct(private Config $config) {}

    public function handle(AuditContext $context, Closure $next): AuditContext
    {
        if (in_array($this->config->driver(), self::AUTO_INDEXING_DRIVERS, true)) {
            return $next($context);
        }

        $findings = [];

        foreach ($context->schema->tables() as $table) {
            foreach ($table->foreignKeys() as $fk) {
                if (! $table->indexesColumn($fk->column)) {
                    $findings[] = $this->makeFinding(
                        table: $table->name,
                        message: sprintf(
                            '<fg=default>%s</> does not auto-index foreign key columns; add index on <fg=default>%s.%s</>',
                            $this->config->driver(),
                            $table->name,
                            $fk->column,
                        ),
                        column: $fk->column,
                        conditional: $table->isConditionallyModified()
                    );
                }
            }
        }

        $context = $context->withFindings($findings);

        return $next($context);
    }
}
