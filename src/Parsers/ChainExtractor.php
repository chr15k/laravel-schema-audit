<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers;

use Chr15k\SchemaAudit\ValueObjects\SourceLocation;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;

final readonly class ChainExtractor
{
    public function __construct(
        private string $path,
    ) {}

    /**
     * @return list<ValueObjects\ColumnChain>
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
                $chains[] = new ValueObjects\ColumnChain(array_reverse($calls));
            }
        }

        return $chains;
    }

    /**
     * @return list<ValueObjects\ColumnCall>|null
     */
    private function unwind(MethodCall $call, int $depth = 0): ?array
    {
        $thisCall = $this->toColumnCall($call);

        if ($call->var instanceof Variable) {
            return [$thisCall];
        }

        if ($call->var instanceof MethodCall) {
            $rest = $this->unwind($call->var, $depth + 1);

            return $rest === null ? null : [$thisCall, ...$rest];
        }

        return null;
    }

    private function toColumnCall(MethodCall $node): ValueObjects\ColumnCall
    {
        $methodName = $node->name instanceof Identifier
            ? $node->name->toString()
            : '';

        $arguments = [];

        foreach ($node->args as $i => $arg) {
            if (! $arg instanceof Node\Arg) {
                continue;
            }

            $key = $arg->name instanceof Identifier
                ? $arg->name->toString()
                : $i;

            $arguments[$key] = $this->resolveValue($arg->value);
        }

        return new ValueObjects\ColumnCall(
            method: $methodName,
            arguments: $arguments,
            location: new SourceLocation(
                path: $this->path,
                line: $node->getStartLine()
            )
        );
    }

    private function resolveValue(Node $node): mixed
    {
        return match (true) {
            $node instanceof String_ => $node->value,

            $node instanceof Node\Expr\Array_ => $this->resolveArray($node),

            $node instanceof Node\Expr\ConstFetch => $node->name->toString() === 'true'
                    ? true
                    : ($node->name->toString() === 'false' ? false : null),

            $node instanceof Node\Scalar\Int_ => $node->value,

            $node instanceof Node\Scalar\Float_ => $node->value,

            $node instanceof ClassConstFetch => $node->class instanceof Name
                && $node->name instanceof Identifier
                ? $node->class->toString().'::'.$node->name->toString()
                : null,

            default => null,
        };
    }

    /**
     * @return array<int|string, mixed>
     */
    private function resolveArray(Node\Expr\Array_ $node): array
    {
        $values = [];

        foreach ($node->items as $item) {
            $values[] = $this->resolveValue($item->value);
        }

        return $values;
    }
}
