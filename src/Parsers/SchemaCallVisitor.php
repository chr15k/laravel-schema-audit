<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers;

use Chr15k\SchemaAudit\Enums\SchemaOperationType;
use Chr15k\SchemaAudit\ValueObjects\SchemaOperation;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

final class SchemaCallVisitor extends NodeVisitorAbstract
{
    /** @var list<SchemaOperation> */
    public array $operations = [];

    public function __construct(
        private readonly ChainExtractor $chainExtractor = new ChainExtractor,
    ) {}

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === 'down') {
            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
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
        $name = ArgReader::stringArgAt($node->args, 0);

        if ($name !== null) {
            $this->operations[] = new SchemaOperation(type: SchemaOperationType::Drop, tableName: $name);
        }

        return null;
    }

    private function recordRename(StaticCall $node): null
    {
        $from = ArgReader::stringArgAt($node->args, 0);
        $to = ArgReader::stringArgAt($node->args, 1);

        if ($from !== null && $to !== null) {
            $this->operations[] = new SchemaOperation(type: SchemaOperationType::Rename, tableName: $from, renameTo: $to);
        }

        return null;
    }

    private function recordCreateOrAlter(StaticCall $node, string $methodName): null
    {
        $name = ArgReader::stringArgAt($node->args, 0);
        $closure = ArgReader::closureArgAt($node->args, 1);

        if ($name === null || ! $closure instanceof Closure) {
            return null;
        }

        $this->operations[] = new SchemaOperation(
            type: $methodName === 'create' ? SchemaOperationType::Create : SchemaOperationType::Alter,
            tableName: $name,
            chains: $this->chainExtractor->extract($closure),
        );

        return null;
    }
}
