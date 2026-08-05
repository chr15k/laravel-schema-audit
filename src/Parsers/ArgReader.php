<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers;

use Chr15k\SchemaAudit\Schema\LaravelConventions;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

final class ArgReader
{
    /**
     * @param  array<int|string, Node\Arg|Node\VariadicPlaceholder>  $args
     */
    public static function stringArgAt(array $args, int $position): ?string
    {
        $arg = $args[$position] ?? null;

        if (! $arg instanceof Node\Arg) {
            return null;
        }

        return self::resolveStringValue($arg->value);
    }

    /**
     * @param  array<int|string, Node\Arg|Node\VariadicPlaceholder>  $args
     */
    public static function closureArgAt(array $args, int $position): ?Closure
    {
        $arg = $args[$position] ?? null;

        if ($arg instanceof Node\Arg && $arg->value instanceof Closure) {
            return $arg->value;
        }

        return null;
    }

    private static function resolveStringValue(Node $node): ?string
    {
        return match (true) {
            $node instanceof String_ => $node->value,

            $node instanceof Variable => '$'.$node->name,

            $node instanceof StaticCall => self::resolveStaticCall($node),

            $node instanceof MethodCall => self::resolveMethodCall($node),

            $node instanceof FuncCall => self::resolveFuncCall($node),

            default => null,
        };
    }

    private static function resolveStaticCall(StaticCall $node): ?string
    {
        if (! $node->name instanceof Identifier || $node->name->toString() !== 'table') {
            return null;
        }

        $arg = $node->args[0] ?? null;

        if (! $arg instanceof Node\Arg) {
            return null;
        }

        return self::resolveStringValue($arg->value);
    }

    private static function resolveMethodCall(MethodCall $node): ?string
    {
        if (! $node->name instanceof Identifier || $node->name->toString() !== 'getTable') {
            return null;
        }

        if (! $node->var instanceof New_) {
            return null;
        }

        if (! $node->var->class instanceof Name) {
            return null;
        }

        return (new LaravelConventions)->tableNameFromModel($node->var->class->toString());
    }

    private static function resolveFuncCall(FuncCall $node): ?string
    {
        if (! $node->name instanceof Name || $node->name->toString() !== 'config') {
            return null;
        }

        $arg = $node->args[0] ?? null;

        if (! $arg instanceof Node\Arg) {
            return null;
        }

        $value = self::resolveStringValue($arg->value);

        if ($value === null) {
            return null;
        }

        $segments = explode('.', $value);

        return end($segments) ?: $value;
    }
}
