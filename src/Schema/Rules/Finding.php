<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Rules;

/**
 * One thing a Rule found wrong with the folded schema. Deliberately flat
 * and serializable — this is what both the JSON and human-readable
 * command output are built from.
 */
final class Finding
{
    public function __construct(
        public readonly string $rule,
        public readonly string $table,
        public readonly string $message,
        public readonly ?string $column = null,
    ) {}

    /**
     * @return array{rule: string, table: string, column: ?string, message: string}
     */
    public function toArray(): array
    {
        return [
            'rule'    => $this->rule,
            'table'   => $this->table,
            'column'  => $this->column,
            'message' => $this->message,
        ];
    }
}
