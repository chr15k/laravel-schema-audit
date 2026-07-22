<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers;

use Chr15k\SchemaAudit\ValueObjects\ColumnCall;
use Chr15k\SchemaAudit\ValueObjects\ColumnChain;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;

final class ChainExtractor
{
    /**
     * @return list<ColumnChain>
     */
    public function extract(Closure $closure): array
    {
        $chains = [];

        foreach ($closure->stmts as $stmt) {
            if (! $stmt instanceof Expression) {
                continue;
            }

            if (! $stmt->expr instanceof MethodCall) {
                continue;
            }

            $calls = $this->unwind($stmt->expr);

            if ($calls !== null) {
                $chains[] = new ColumnChain(array_reverse($calls));
            }
        }

        return $chains;
    }

    /**
     * @return list<ColumnCall>|null
     */
    private function unwind(MethodCall $call, int $depth = 0): ?array
    {
        if ($depth > 20) {
            return null; // safety valve against unexpectedly deep/cyclic ASTs
        }

        $thisCall = $this->toColumnCall($call);

        if ($call->var instanceof Variable && $call->var->name === 'table') {
            return [$thisCall];
        }

        if ($call->var instanceof MethodCall) {
            $rest = $this->unwind($call->var, $depth + 1);

            return $rest === null ? null : [$thisCall, ...$rest];
        }

        return null;
    }

    private function toColumnCall(MethodCall $node): ColumnCall
    {
        $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : '';

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

        return new ColumnCall(
            method: $methodName,
            stringArgs: $stringArgs,
            arrayArgs: $arrayArgs
        );
    }
}
