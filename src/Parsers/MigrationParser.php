<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use RuntimeException;

final readonly class MigrationParser
{
    /**
     * @return list<ValueObjects\SchemaOperation>
     */
    public function parseFile(string $path): array
    {
        $code = @file_get_contents($path);

        if ($code === false) {
            throw new RuntimeException('Unable to read migration file: '.$path);
        }

        return $this->parseCode($code, $path);
    }

    /**
     * @return list<ValueObjects\SchemaOperation>
     */
    public function parseCode(string $code, string $path): array
    {
        $ast = (new ParserFactory)
            ->createForNewestSupportedVersion()
            ->parse($code) ?? [];

        $visitor = new SchemaCallVisitor(path: $path);

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new ParentConnectingVisitor);
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->operations();
    }
}
