<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers;

use Chr15k\SchemaAudit\Schema\LaravelConventions;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

final class ArgReader
{
    /**
     * @param  array<int|string, Node\Arg|Node\VariadicPlaceholder>  $args
     * @return list<string>
     */
    public static function stringsArgAt(
        array $args,
        int $position,
        ?Node $context = null
    ): array {
        $arg = $args[$position] ?? null;

        if (! $arg instanceof Node\Arg) {
            return [];
        }

        return self::resolveStrings($arg->value, $context);
    }

    /**
     * @param  array<int|string, Node\Arg|Node\VariadicPlaceholder>  $args
     */
    public static function stringArgAt(
        array $args,
        int $position,
        ?Node $context = null
    ): ?string {
        $values = self::stringsArgAt($args, $position, $context);

        return count($values) === 1 ? $values[0] : null;
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

    private static function resolveStrings(Node $node, ?Node $context = null): array
    {
        return match (true) {
            $node instanceof String_ => [$node->value],

            $node instanceof Node\Expr\Array_ => self::resolveArrayValue($node),

            $node instanceof Variable => self::resolveVariable($node, $context),

            $node instanceof StaticCall => self::resolveStaticCall($node),

            $node instanceof MethodCall => self::resolveMethodCall($node),

            $node instanceof FuncCall => self::resolveFuncCall($node),

            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private static function resolveFuncCall(FuncCall $node): array
    {
        if (
            ! $node->name instanceof Name ||
            $node->name->toString() !== 'collect'
        ) {
            return [];
        }

        $arg = $node->args[0] ?? null;

        if (! $arg instanceof Node\Arg) {
            return [];
        }

        return self::resolveStrings(
            $arg->value,
            $node,
        );
    }

    /**
     * @return list<string>
     */
    private static function resolveArrayValue(Node\Expr\Array_ $array): array
    {
        $values = [];

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->value instanceof String_) {
                $values[] = $item->value->value;
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private static function resolveVariable(
        Variable $node,
        ?Node $context,
    ): array {
        if (
            ! is_string($node->name)
            || ! $context instanceof Node
        ) {
            return [];
        }

        // foreach ($tables as $table)
        if ($values = self::resolveForeachVariable($node, $context)) {
            return $values;
        }

        // collect(...)->each(function ($table) {})
        foreach (
            self::ancestorClosures($context) as $closure
        ) {
            if ($values = self::resolveClosureParameter($node, $closure)) {
                return $values;
            }
        }

        // $tables = [...]
        return self::resolveAssignedVariable($node->name, $context);
    }

    private static function parentStatementContainer(Node $node): ?Node
    {
        for (
            $current = self::parentOf($node);
            $current instanceof Node;
            $current = self::parentOf($current)
        ) {
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

    /**
     * @return list<Closure>
     */
    private static function ancestorClosures(Node $node): array
    {
        $closures = [];

        for (
            $current = $node;
            $current instanceof Node;
            $current = self::parentOf($current)
        ) {
            if ($current instanceof Closure) {
                $closures[] = $current;
            }
        }

        return $closures;
    }

    private static function statementIndexInScope(
        Node $scope,
        Node $context,
    ): ?int {
        foreach ($scope->stmts as $index => $statement) {
            for (
                $current = $context;
                $current instanceof Node;
                $current = self::parentOf($current)
            ) {
                if ($current === $statement) {
                    return $index;
                }
            }
        }

        return null;
    }

    private static function nearestStatementContainer(Node $node): ?Node
    {
        for (
            $current = $node;
            $current instanceof Node;
            $current = self::parentOf($current)
        ) {
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

    /**
     * @return list<string>
     */
    private static function resolveAssignedVariable(
        string $name,
        Node $context,
    ): array {
        for (
            $scope = self::nearestStatementContainer($context);
            $scope instanceof Node;
            $scope = self::parentStatementContainer($scope)
        ) {
            if (! property_exists($scope, 'stmts')) {
                continue;
            }
            if (! is_array($scope->stmts)) {
                continue;
            }
            $statementIndex = self::statementIndexInScope(
                $scope,
                $context,
            );

            if ($statementIndex === null) {
                continue;
            }

            for ($i = $statementIndex - 1; $i >= 0; $i--) {
                $statement = $scope->stmts[$i];

                if (! $statement instanceof Node\Stmt\Expression) {
                    continue;
                }

                if (! $statement->expr instanceof Node\Expr\Assign) {
                    continue;
                }

                $assign = $statement->expr;

                if (! $assign->var instanceof Variable) {
                    continue;
                }
                if (! is_string($assign->var->name)) {
                    continue;
                }
                if ($assign->var->name !== $name) {
                    continue;
                }

                return self::resolveStrings(
                    $assign->expr,
                    $statement,
                );
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function resolveClosureParameter(
        Variable $variable,
        Closure $closure
    ): array {
        foreach ($closure->params as $param) {
            if (
                $param->var instanceof Variable &&
                $param->var->name === $variable->name
            ) {
                return self::resolveCallbackValues($closure);
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function resolveForeachVariable(
        Variable $node,
        Node $context
    ): array {
        if (! is_string($node->name)) {
            return [];
        }

        for (
            $current = $context;
            $current instanceof Node;
            $current = self::parentOf($current)
        ) {
            if (! $current instanceof Node\Stmt\Foreach_) {
                continue;
            }

            if (
                $current->valueVar instanceof Variable &&
                $current->valueVar->name === $node->name
            ) {
                return self::resolveStrings(
                    $current->expr,
                    $current,
                );
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function resolveCallbackValues(
        Closure $closure
    ): array {
        $each = self::nearestEachCall($closure);

        if (! $each instanceof MethodCall) {
            return [];
        }

        return self::resolveEachCollection($each);
    }

    private static function nearestEachCall(Closure $closure): ?MethodCall
    {
        for (
            $current = self::parentOf($closure);
            $current instanceof Node;
            $current = self::parentOf($current)
        ) {
            if (
                $current instanceof MethodCall &&
                $current->name instanceof Identifier &&
                $current->name->toString() === 'each'
            ) {
                return $current;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    /**
     * @return list<string>
     */
    private static function resolveEachCollection(MethodCall $each): array
    {
        $collection = $each->var;

        if (! $collection instanceof FuncCall) {
            return [];
        }

        if (
            ! $collection->name instanceof Name ||
            $collection->name->toString() !== 'collect'
        ) {
            return [];
        }

        $arg = $collection->args[0] ?? null;

        if (! $arg instanceof Node\Arg) {
            return [];
        }

        if (! $arg->value instanceof Node\Expr\Array_) {
            return self::resolveStrings(
                $arg->value,
                $each,
            );
        }

        $values = [];

        foreach ($arg->value->items as $item) {
            if (
                $item !== null &&
                $item->value instanceof String_
            ) {
                $values[] = $item->value->value;
            }
        }

        return $values;
    }

    private static function parentOf(Node $node): ?Node
    {
        $parent = $node->getAttribute('parent');

        return $parent instanceof Node ? $parent : null;
    }

    /**
     * @return list<string>
     */
    private static function resolveStaticCall(StaticCall $node): array
    {
        if (
            ! $node->name instanceof Identifier ||
            $node->name->toString() !== 'table'
        ) {
            return [];
        }

        $arg = $node->args[0] ?? null;

        if (! $arg instanceof Node\Arg) {
            return [];
        }

        return self::resolveStrings($arg->value);
    }

    /**
     * @return list<string>
     */
    private static function resolveMethodCall(MethodCall $node): array
    {
        if (
            ! $node->name instanceof Identifier ||
            $node->name->toString() !== 'getTable'
        ) {
            return [];
        }

        if (! $node->var instanceof Node\Expr\New_) {
            return [];
        }

        if (! $node->var->class instanceof Name) {
            return [];
        }

        return [
            (new LaravelConventions)
                ->tableNameFromModel($node->var->class->toString()),
        ];
    }
}
