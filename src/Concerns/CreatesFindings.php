<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Concerns;

use Chr15k\SchemaAudit\Contracts\SchemaReference;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SourceLocation;
use Chr15k\SchemaAudit\ValueObjects\Finding;

trait CreatesFindings
{
    protected function defaultCode(): string
    {
        return str(class_basename(static::class))
            ->replaceLast('Rule', '')
            ->snake()
            ->toString();
    }

    protected function defaultSeverity(): Severity
    {
        return Severity::Warning;
    }

    /**
     * @param  array<int, SchemaReference>  $related
     * @param  string|list<string>|null  $columns
     */
    protected function makeFinding(
        string $table,
        string $message,
        null|string|array $columns = null,
        ?string $code = null,
        ?Severity $severity = null,
        ?SchemaGuard $guard = null,
        ?SourceLocation $location = null,
        array $related = [],
    ): Finding {
        return new Finding(
            code: $code ?? $this->defaultCode(),
            table: $table,
            message: $message,
            columns: $columns,
            severity: $severity ?? $this->defaultSeverity(),
            guard: $guard,
            location: $location,
            related: $related
        );
    }
}
