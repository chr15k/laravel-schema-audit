<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\Schema\ValueObjects\ForeignKey;
use Chr15k\SchemaAudit\Support\Config;
use Chr15k\SchemaAudit\ValueObjects\Finding;
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
                if ($table->hasNoIndexFor($fk->columns)) {
                    $findings[] = $this->unindexedForeignKeyFinding($table, $fk);
                }
            }
        }

        return $next($context->withFindings($findings));
    }

    private function unindexedForeignKeyFinding(TableSchema $table, ForeignKey $fk): Finding
    {
        $columns = is_array($fk->columns)
            ? implode(', ', $fk->columns)
            : $fk->columns;

        $message = is_array($fk->columns)
            ? sprintf(
                '<fg=default>%s</> does not auto-index composite foreign key columns; add a composite index on <fg=default>%s (%s)</>',
                $this->config->driver(),
                $table->name,
                $columns,
            )
            : sprintf(
                '<fg=default>%s</> does not auto-index foreign key columns; add an index on <fg=default>%s.%s</>',
                $this->config->driver(),
                $table->name,
                $columns,
            );

        return $this->makeFinding(
            table: $table->name,
            message: $message,
            columns: $columns,
            guard: $fk->guard(),
            location: $fk->location(),
        );
    }
}
