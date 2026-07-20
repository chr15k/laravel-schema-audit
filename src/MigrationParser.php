<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Parsers\SchemaCallVisitor;
use Chr15k\SchemaAudit\ValueObjects\SchemaOperation;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use RuntimeException;

/**
 * Parses a single migration file and returns a list of raw SchemaOperations
 * (one per Schema::create/table/drop/rename call found in the file's up()
 * method). The SchemaBuilder is responsible for folding these across
 * multiple files — this class only extracts what one file literally says.
 *
 * This is a thin entrypoint: AST traversal and per-call interpretation
 * live in Schema\Parsers\SchemaCallVisitor and Schema\Parsers\ChainExtractor.
 */
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
