<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Schema\Schema;

final readonly class NoPrimaryKeyRule extends Rule
{
    public function check(Schema $schema): array
    {
        $findings = [];

        foreach ($schema->tables() as $table) {
            if (! $table->hasPrimaryKey()) {
                $findings[] = $this->makeFinding(
                    table: $table->name,
                    message: 'No primary key found. Add an auto-incrementing id() or define an explicit primary(...) for this table.',
                    severity: Severity::Error,
                );
            }
        }

        return $findings;
    }
}
