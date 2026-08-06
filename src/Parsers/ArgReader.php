<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers;

use Chr15k\SchemaAudit\Schema\LaravelConventions;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
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
    public static function stringArgAt(array $args, int $position, ?Node $context = null): ?string
    {
        $arg = $args[$position] ?? null;

        if (! $arg instanceof Node\Arg) {
            return null;
        }

        return self::resolveStringValue($arg->value, $context);
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

    private static function resolveStringValue(Node $node, ?Node $context = null): ?string
    {
        return match (true) {
            $node instanceof String_ => $node->value,

            $node instanceof Variable => self::resolveVariableValue($node, $context),

            $node instanceof StaticCall => self::resolveStaticCall($node),

            $node instanceof MethodCall => self::resolveMethodCall($node),

            $node instanceof FuncCall => self::resolveFuncCall($node),

            default => null,
        };
    }

    private static function resolveVariableValue(Variable $node, ?Node $context = null): ?string
    {
        $name = $node->name;

        if (! is_string($name)) {
            return null;
        }

        if (! $context instanceof Node) {
            return '$'.$name;
        }

        $scope = self::nearestScope($context);

        if (! $scope instanceof Node || ! property_exists($scope, 'stmts') || ! is_array($scope->stmts)) {
            return '$'.$name;
        }

        $statementIndex = self::statementIndexInScope($scope, $context);

        if ($statementIndex === null) {
            return '$'.$name;
        }

        for ($i = $statementIndex - 1; $i >= 0; $i--) {
            $statement = $scope->stmts[$i];

            if ($statement instanceof Node\Stmt\Expression && $statement->expr instanceof Assign) {
                $assignment = $statement->expr;

                if ($assignment->var instanceof Variable && is_string($assignment->var->name) && $assignment->var->name === $name) {
                    return self::resolveStringValue($assignment->expr, $context);
                }
            }

            if ($statement instanceof Assign && $statement->var instanceof Variable && is_string($statement->var->name) && $statement->var->name === $name) {
                return self::resolveStringValue($statement->expr, $context);
            }
        }

        return '$'.$name;
    }

    private static function nearestScope(Node $node): ?Node
    {
        for ($current = $node; $current instanceof Node; $current = self::parentOf($current)) {
            if (
                $current instanceof Node\Stmt\ClassMethod
                || $current instanceof Node\Stmt\Function_
                || $current instanceof Closure
                || $current instanceof Node\Stmt\If_
                || $current instanceof Node\Stmt\ElseIf_
                || $current instanceof Node\Stmt\Else_
                || $current instanceof Node\Stmt\Foreach_
                || $current instanceof Node\Stmt\For_
                || $current instanceof Node\Stmt\While_
                || $current instanceof Node\Stmt\Switch_
                || $current instanceof Node\Stmt\Case_
            ) {
                return $current;
            }
        }

        return null;
    }

    private static function statementIndexInScope(Node $scope, Node $context): ?int
    {
        if (! property_exists($scope, 'stmts') || ! is_array($scope->stmts)) {
            return null;
        }

        foreach ($scope->stmts as $index => $statement) {
            for ($current = $context; $current instanceof Node; $current = self::parentOf($current)) {
                if ($current === $statement) {
                    return $index;
                }
            }
        }

        return null;
    }

    private static function parentOf(Node $node): ?Node
    {
        $parent = $node->getAttribute('parent');

        return $parent instanceof Node ? $parent : null;
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
