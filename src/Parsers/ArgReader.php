<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Scalar\String_;

final class ArgReader
{
    /**
     * @param  array<int|string, Node\Arg|Node\VariadicPlaceholder>  $args
     */
    public static function stringArgAt(array $args, int $position): ?string
    {
        $arg = $args[$position] ?? null;

        if ($arg instanceof Node\Arg && $arg->value instanceof String_) {
            return $arg->value->value;
        }

        return null;
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
}
