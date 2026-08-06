<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Sources;

use Chr15k\SchemaAudit\Contracts\SchemaSource;
use Chr15k\SchemaAudit\Migrations\MigrationLocator;
use Chr15k\SchemaAudit\Parsers\MigrationParser;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SchemaOperation;

final readonly class MigrationSource implements SchemaSource
{
    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        private MigrationLocator $locator,
        private MigrationParser $parser,
        private array $paths,
    ) {}

    /**
     * @return iterable<SchemaOperation>
     */
    public function operations(): iterable
    {
        foreach ($this->locator->files($this->paths) as $file) {
            yield from $this->parser->parseFile($file);
        }
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        return $this->locator->files($this->paths);
    }
}
