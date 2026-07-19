<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Data;

/**
 * A single `$table->method(...)` call as literally written — e.g.
 * ColumnCall('string', ['name'], []) for $table->string('name'), or
 * ColumnCall('index', [], ['a', 'b']) for $table->index(['a', 'b']).
 */
final readonly class ColumnCall
{
    /**
     * @param  list<string>  $stringArgs
     * @param  list<string>  $arrayArgs
     */
    public function __construct(
        public string $method,
        public array $stringArgs,
        public array $arrayArgs,
    ) {}
}
