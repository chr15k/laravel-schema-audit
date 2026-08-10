<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Concerns;

use Chr15k\SchemaAudit\Contracts\SchemaReference;
use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\Schema\TableSchema;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Chr15k\SchemaAudit\ValueObjects\SourceLocation;

trait CreatesFindings
{
    /**
     * Create a new finding.
     *
     * @param  string|list<string>|null  $columns
     * @param  list<SchemaReference>  $related
     */
    protected function finding(
        TableSchema $table,
        string $message,
        string|array|null $columns = null,
        ?Severity $severity = null,
        ?SchemaGuard $guard = null,
        ?SourceLocation $location = null,
        array $related = [],
    ): Finding {
        /** @var list<string> */
        $columns = match (true) {
            $columns === null   => [],
            is_string($columns) => [$columns],
            default             => $columns,
        };

        $firstColumn = $columns !== []
            ? $table->column($columns[0])
            : null;

        return new Finding(
            code: $this->defaultCode(),
            table: $table->name,
            message: $message,
            columns: $columns,
            severity: $severity ?? $this->defaultSeverity(),
            guard: $guard ?? $firstColumn?->guard() ?? $table->guard(),
            location: $location ?? $firstColumn?->location() ?? $table->location(),
            related: $related,
        );
    }

    /**
     * Create a warning finding.
     *
     * @param  string|list<string>|null  $columns
     * @param  list<SchemaReference>  $related
     */
    protected function warning(
        TableSchema $table,
        string $message,
        string|array|null $columns = null,
        ?SchemaGuard $guard = null,
        ?SourceLocation $location = null,
        array $related = [],
    ): Finding {
        return $this->finding(
            table: $table,
            columns: $columns,
            message: $message,
            severity: Severity::Warning,
            guard: $guard,
            location: $location,
            related: $related
        );
    }

    /**
     * Create an error finding.
     *
     * @param  string|list<string>|null  $columns
     * @param  list<SchemaReference>  $related
     */
    protected function error(
        TableSchema $table,
        string $message,
        string|array|null $columns = null,
        ?SchemaGuard $guard = null,
        ?SourceLocation $location = null,
        array $related = [],
    ): Finding {
        return $this->finding(
            table: $table,
            columns: $columns,
            message: $message,
            severity: Severity::Error,
            guard: $guard,
            location: $location,
            related: $related
        );
    }

    /**
     * Create an info finding.
     *
     * @param  string|list<string>|null  $columns
     * @param  list<SchemaReference>  $related
     */
    protected function info(
        TableSchema $table,
        string $message,
        string|array|null $columns = null,
        ?SchemaGuard $guard = null,
        ?SourceLocation $location = null,
        array $related = [],
    ): Finding {
        return $this->finding(
            table: $table,
            columns: $columns,
            message: $message,
            severity: Severity::Info,
            guard: $guard,
            location: $location,
            related: $related
        );
    }

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
}
