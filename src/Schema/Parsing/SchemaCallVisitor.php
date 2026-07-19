<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\Parsing;

use Chr15k\SchemaAudit\Schema\SchemaOperation;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Walks a migration file's AST looking for Schema:: static calls and turns
 * each one into a SchemaOperation. Chain-walking inside create()/table()
 * closures is delegated to ChainExtractor — this class is only responsible
 * for recognising WHICH Schema:: call it is and what table(s) it names.
 */
final class SchemaCallVisitor extends NodeVisitorAbstract
{
    /** @var list<SchemaOperation> */
    public array $operations = [];

    public function __construct(
        private readonly ChainExtractor $chainExtractor = new ChainExtractor,
    ) {}

    public function enterNode(Node $node)
    {
        // A migration's down() method is the ROLLBACK — it commonly
        // contains Schema::dropIfExists(...) for a table that was never
        // actually dropped in forward history. Skip it entirely so its
        // Schema:: calls never get folded in as if they were real
        // forward operations.
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === 'down') {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if (! $node instanceof StaticCall) {
            return null;
        }

        $className = $node->class instanceof Node\Name ? $node->class->toString() : null;

        if ($className !== 'Schema') {
            return null;
        }

        $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        return match (true) {
            $methodName === 'dropIfExists' || $methodName === 'drop' => $this->recordDrop($node),
            $methodName === 'rename'                                 => $this->recordRename($node),
            in_array($methodName, ['create', 'table'], true)         => $this->recordCreateOrAlter($node, $methodName),
            default                                                  => null,
        };
    }

    private function recordDrop(StaticCall $node): null
    {
        $tableName = ArgReader::stringArgAt($node->args, 0);

        if ($tableName !== null) {
            $this->operations[] = new SchemaOperation(type: SchemaOperation::TYPE_DROP, tableName: $tableName);
        }

        return null;
    }

    private function recordRename(StaticCall $node): null
    {
        $from = ArgReader::stringArgAt($node->args, 0);
        $to = ArgReader::stringArgAt($node->args, 1);

        if ($from !== null && $to !== null) {
            $this->operations[] = new SchemaOperation(type: SchemaOperation::TYPE_RENAME, tableName: $from, renameTo: $to);
        }

        return null;
    }

    private function recordCreateOrAlter(StaticCall $node, string $methodName): null
    {
        $tableName = ArgReader::stringArgAt($node->args, 0);
        $closure = ArgReader::closureArgAt($node->args, 1);

        if ($tableName === null || ! $closure instanceof Closure) {
            return null;
        }

        $this->operations[] = new SchemaOperation(
            type: $methodName === 'create' ? SchemaOperation::TYPE_CREATE : SchemaOperation::TYPE_ALTER,
            tableName: $tableName,
            chains: $this->chainExtractor->extract($closure),
        );

        return null;
    }
}
