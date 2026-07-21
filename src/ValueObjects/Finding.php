<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\ValueObjects;

use Chr15k\SchemaAudit\Enums\Severity;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;

final readonly class Finding implements Arrayable, JsonSerializable, Stringable
{
    public function __construct(
        public string $code,
        public string $table,
        public string $message,
        public ?string $column = null,
        public Severity $severity = Severity::Warning,
    ) {}

    public function __toString(): string
    {
        return str($this->code)->headline()->toString();
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{code: string, table: string, column: ?string, message: string, severity: string}
     */
    public function toArray(): array
    {
        return [
            'code'     => $this->code,
            'table'    => $this->table,
            'column'   => $this->column,
            'message'  => $this->message,
            'severity' => $this->severity->value,
        ];
    }
}
