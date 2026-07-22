<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Concerns;

use Chr15k\SchemaAudit\Enums\Severity;
use Chr15k\SchemaAudit\ValueObjects\Finding;

/**
 * Shared Finding-construction boilerplate for Rule implementations. Use
 * this trait alongside `implements Rule` — it does not replace the
 * interface, it just removes the need to repeat the rule's own name and
 * default severity at every makeFinding() call site inside check().
 *
 * final class MyRule extends Rule
 * {
 *     public function check(Schema $schema): array
 *     {
 *         $findings = [];
 *
 *         foreach ($tables as $table) {
 *             if (/* ...your condition... * /) {
 *                 $findings[] = $this->makeFinding($table, 'some_column', 'Something is wrong.');
 *             }

 *         }
 *
 *         return $findings;
 *     }
 * }
 */
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

    protected function makeFinding(
        string $table,
        string $message,
        ?string $column = null,
        ?string $code = null,
        ?Severity $severity = null,
    ): Finding {
        return new Finding(
            code: $code ?? $this->defaultCode(),
            table: $table,
            message: $message,
            column: $column,
            severity: $severity ?? $this->defaultSeverity(),
        );
    }
}
