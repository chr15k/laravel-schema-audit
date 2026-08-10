<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;

final readonly class MissingPrimaryKeyRule extends Rule
{
    protected function check(AuditContext $context): iterable
    {
        foreach ($context->schema->tables() as $table) {
            if (! $table->hasPrimaryKey()) {
                yield $this->error(
                    table: $table,
                    message: sprintf('Table %s has no primary key', $table->name)
                );
            }
        }
    }
}
