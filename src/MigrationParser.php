<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Parsers\SchemaCallVisitor;
use Chr15k\SchemaAudit\ValueObjects\SchemaOperation;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use RuntimeException;

final readonly class MigrationParser
{
    /**
     * @return list<SchemaOperation>
     */
    public function parseFile(string $path): array
    {
        $code = file_get_contents($path);

        if ($code === false) {
            throw new RuntimeException('Unable to read migration file: '.$path);
        }

        return $this->parseCode($code);
    }

    /**
     * @return list<SchemaOperation>
     */
    public function parseCode(string $code): array
    {
        $ast = (new ParserFactory)
            ->createForNewestSupportedVersion()
            ->parse($code) ?? [];

        $visitor = new SchemaCallVisitor;

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->operations;
    }
}
