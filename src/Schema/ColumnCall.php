<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

/**
 * A single `$table->method(...)` call as literally written — e.g.
 * ColumnCall('string', ['name'], []) for $table->string('name'), or
 * ColumnCall('index', [], ['a', 'b']) for $table->index(['a', 'b']).
 */
final class ColumnCall
{
    /**
     * @param  list<string>  $stringArgs
     * @param  list<string>  $arrayArgs
     */
    public function __construct(
        public readonly string $method,
        public readonly array $stringArgs,
        public readonly array $arrayArgs,
    ) {}
}
