<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers\ValueObjects;

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
