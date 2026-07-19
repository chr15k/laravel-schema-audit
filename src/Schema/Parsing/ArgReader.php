<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Parsing;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Scalar\String_;

/**
 * Small, stateless helpers for pulling typed values out of PHP-Parser Arg
 * nodes. Exists purely to keep the visitor/chain-extraction classes free
 * of this kind of "is this Arg a String_, and if so give me its value"
 * boilerplate.
 */
final class ArgReader
{
    /**
     * @param  list<Node\Arg|Node\VariadicPlaceholder>  $args
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
     * @param  list<Node\Arg|Node\VariadicPlaceholder>  $args
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
