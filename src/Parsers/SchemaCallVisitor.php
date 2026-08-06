<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers;

use Chr15k\SchemaAudit\Enums\SchemaGuard;
use Chr15k\SchemaAudit\Enums\SchemaOperationType;
use Chr15k\SchemaAudit\Parsers\ValueObjects\SourceLocation;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

final class SchemaCallVisitor extends NodeVisitorAbstract
{
    /** @var list<ValueObjects\SchemaOperation> */
    private array $operations = [];

    /** @var list<SchemaGuard> */
    private array $guardStack = [];

    private readonly ChainExtractor $chainExtractor;

    public function __construct(
        private readonly string $path,
    ) {
        $this->chainExtractor = new ChainExtractor($path);
    }

    /**
     * @return list<ValueObjects\SchemaOperation>
     */
    public function operations(): array
    {
        return $this->operations;
    }

    public function leaveNode(Node $node): ?int
    {
        if (
            $node instanceof Node\Stmt\If_
            || $node instanceof Node\Stmt\ElseIf_
            || $node instanceof Node\Stmt\Else_
        ) {
            array_pop($this->guardStack);
        }

        return null;
    }

    public function enterNode(Node $node): ?int
    {
        if (($guard = $this->guardForNode($node)) instanceof SchemaGuard) {
            $this->guardStack[] = $guard;
        }

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

        return match ($methodName) {
            'dropIfExists', 'drop' => $this->recordDrop($node),
            'rename'               => $this->recordRename($node),
            'create', 'table'      => $this->recordCreateOrAlter($node, $methodName),
            default                => null,
        };
    }

    private function guardForNode(Node $node): ?SchemaGuard
    {
        return match (true) {
            $node instanceof Node\Stmt\If_,
            $node instanceof Node\Stmt\ElseIf_ => $this->guard($node->cond),

            $node instanceof Node\Stmt\Else_ => SchemaGuard::Unknown,

            default => null,
        };
    }

    private function currentGuard(): ?SchemaGuard
    {
        foreach (array_reverse($this->guardStack) as $guard) {
            if ($guard === SchemaGuard::Unknown) {
                return SchemaGuard::Unknown;
            }
        }

        return end($this->guardStack) ?: null;
    }

    private function guard(Node $node): SchemaGuard
    {
        $negated = $node instanceof Node\Expr\BooleanNot;

        if ($negated) {
            $node = $node->expr;
        }

        if (! $node instanceof StaticCall) {
            return SchemaGuard::Unknown;
        }

        if (! $node->class instanceof Node\Name || $node->class->toString() !== 'Schema') {
            return SchemaGuard::Unknown;
        }

        $method = $node->name instanceof Node\Identifier
            ? $node->name->toString()
            : null;

        return match ([$method, $negated]) {
            ['hasTable', false] => SchemaGuard::HasTable,
            ['hasTable', true]  => SchemaGuard::MissingTable,

            ['hasColumn', false] => SchemaGuard::HasColumn,
            ['hasColumn', true]  => SchemaGuard::MissingColumn,

            default => SchemaGuard::Unknown,
        };
    }

    private function recordDrop(StaticCall $node): null
    {
        if ($name = ArgReader::stringArgAt($node->args, 0, $node)) {
            $this->addOperation(
                type: SchemaOperationType::Drop,
                tableName: $name,
                line: $node->getStartLine()
            );
        }

        return null;
    }

    /**
     * @param  list<ValueObjects\ColumnChain>  $chains
     */
    private function addOperation(
        SchemaOperationType $type,
        string $tableName,
        ?string $renameTo = null,
        array $chains = [],
        int $line = -1,
    ): void {
        $this->operations[] = new ValueObjects\SchemaOperation(
            type: $type,
            tableName: $tableName,
            chains: $chains,
            renameTo: $renameTo,
            guard: $this->currentGuard(),
            location: new SourceLocation($this->path, $line)
        );
    }

    private function recordRename(StaticCall $node): null
    {
        $from = ArgReader::stringArgAt($node->args, 0, $node);
        $to = ArgReader::stringArgAt($node->args, 1, $node);

        if ($from && $to) {
            $this->addOperation(
                type: SchemaOperationType::Rename,
                tableName: $from,
                renameTo: $to,
                line: $node->getStartLine()
            );
        }

        return null;
    }

    private function recordCreateOrAlter(StaticCall $node, string $methodName): null
    {
        $names = ArgReader::stringsArgAt($node->args, 0, $node);

        if ($names === []) {
            return null;
        }

        $closure = ArgReader::closureArgAt($node->args, 1);

        if (! $closure instanceof Closure) {
            return null;
        }

        $chains = $this->chainExtractor->extract($closure);

        foreach ($names as $name) {
            $this->addOperation(
                type: $methodName === 'create'
                    ? SchemaOperationType::Create
                    : SchemaOperationType::Alter,
                tableName: $name,
                chains: $chains,
                line: $node->getStartLine()
            );
        }

        return null;
    }
}
