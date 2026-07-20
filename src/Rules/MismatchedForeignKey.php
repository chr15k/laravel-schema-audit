<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Rules;

use Chr15k\SchemaAudit\Contracts\Rule;

final class MismatchedForeignKey implements Rule
{
    public function check(array $tables): array
    {
        //

        return [];
    }
}
