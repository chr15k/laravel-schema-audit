<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use RuntimeException;

/**
 * Parses a single migration file and returns a list of raw "operations"
 * (one per Schema::create / Schema::table call found in the file). The
 * SchemaBuilder is responsible for folding these across multiple files —
 * this class only extracts what one file literally says.
 */
final class MigrationParser
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
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code) ?? [];

        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var list<SchemaOperation> */
            public array $operations = [];

            public function enterNode(Node $node)
            {
                if (! $node instanceof StaticCall) {
                    return null;
                }

                $className = $node->class instanceof Node\Name ? $node->class->toString() : null;

                if ($className !== 'Schema') {
                    return null;
                }

                $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

                if (! in_array($methodName, ['create', 'table'], true)) {
                    return null;
                }

                $tableName = $this->firstStringArg($node);
                $closure = $this->closureArg($node);

                if ($tableName === null || ! $closure instanceof Closure) {
                    return null;
                }

                $operations = $this->extractColumnCalls($closure);

                $this->operations[] = new SchemaOperation(
                    type: $methodName === 'create' ? SchemaOperation::TYPE_CREATE : SchemaOperation::TYPE_ALTER,
                    tableName: $tableName,
                    columnCalls: $operations,
                );

                return null;
            }

            private function firstStringArg(StaticCall $call): ?string
            {
                $arg = $call->args[0] ?? null;

                if ($arg instanceof Node\Arg && $arg->value instanceof String_) {
                    return $arg->value->value;
                }

                return null;
            }

            private function closureArg(StaticCall $call): ?Closure
            {
                $arg = $call->args[1] ?? null;

                if ($arg instanceof Node\Arg && $arg->value instanceof Closure) {
                    return $arg->value;
                }

                return null;
            }

            /**
             * Walk the closure body and collect every `$table->method(...)`
             * chain as a flat ColumnCall (method name + string/array args).
             * We deliberately keep this generic rather than special-casing
             * every Blueprint method — the SchemaBuilder decides meaning.
             *
             * @return list<ColumnCall>
             */
            private function extractColumnCalls(Closure $closure): array
            {
                $inner = new class extends NodeVisitorAbstract
                {
                    /** @var list<ColumnCall> */
                    public array $calls = [];

                    public function enterNode(Node $node)
                    {
                        if (! $node instanceof MethodCall) {
                            return null;
                        }

                        // Only interested in calls on the blueprint param,
                        // e.g. $table->string('name') — identified by the
                        // variable name being 'table' by convention. Chained
                        // calls like ->string('x')->nullable() are walked
                        // as separate MethodCall nodes and both captured.
                        $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

                        if ($methodName === null) {
                            return null;
                        }

                        $stringArgs = [];
                        $arrayArgs = [];

                        foreach ($node->args as $arg) {
                            if (! $arg instanceof Node\Arg) {
                                continue;
                            }

                            if ($arg->value instanceof String_) {
                                $stringArgs[] = $arg->value->value;
                            }

                            if ($arg->value instanceof Node\Expr\Array_) {
                                foreach ($arg->value->items as $item) {
                                    if ($item->value instanceof String_) {
                                        $arrayArgs[] = $item->value->value;
                                    }
                                }
                            }
                        }

                        $this->calls[] = new ColumnCall(
                            method: $methodName,
                            stringArgs: $stringArgs,
                            arrayArgs: $arrayArgs,
                        );

                        return null;
                    }
                };

                $traverser = new NodeTraverser;
                $traverser->addVisitor($inner);
                $traverser->traverse($closure->stmts);

                return $inner->calls;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->operations;
    }
}
