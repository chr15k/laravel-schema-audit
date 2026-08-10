<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Data\AuditContext;

final readonly class RedundantIndexRule extends Rule
{
    protected function check(AuditContext $context): iterable
    {
        foreach ($context->schema->tables() as $table) {
            foreach ($table->indexes()->redundant() as $redundant) {
                yield $this->warning(
                    table: $table,
                    message: sprintf(
                        'Index %s is covered by %s',
                        $redundant->index->name,
                        $redundant->coveredBy->name,
                    ),
                    columns: $redundant->index->columns,
                    location: $redundant->index->location,
                    related: [$redundant->coveredBy]
                );
            }
        }
    }
}
