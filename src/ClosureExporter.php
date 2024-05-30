<?php

namespace Bermuda\VarExport;

use PhpParser\Builder\Function_;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FindingVisitor;
use PhpParser\NodeVisitor\FirstFindingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as NodeClosure;
use PhpParser\Node\Name;
use function Bermuda\Stdlib\array_last;

final class ClosureExporter implements ClosureExporterInterface
{
    private static NodeFinder|null $nodeFinder = null;
    public function exportClosure(\Closure $closure): string
    {
        $code = $this->readFile(($reflector = new \ReflectionFunction($closure))
            ->getFileName());
        $ast = $this->createParser()->parse($code);

        $node = static::find($ast, $this->createFindCallback($reflector));

        if (!$node) {
            if ($reflector->getClosureCalledClass() !== null) {
                $cls = $reflector->getClosureCalledClass()->name;
                $method = $reflector->getShortName();
                return "fn() => $cls::$method(...\get_function_args())";
            }

            throw new ExportException("Can't export closure", $closure);
        }

        $visitor = $this->getVisitor(
            $this->getNamespace($ast),
            $this->getUseNodes($ast),
            $this->findClassName(...),
            $reflector->getFileName()
        );

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor, $this->getUseNodes($ast));
        $traverser->traverse([$node]);

        return $this->getPrinter()->prettyPrintExpr($node);
    }

    private function findClassName(Node $node):? Node
    {
        if ($node instanceof Node\Stmt\Class_) {
            return $node;
        };

        $nextNode = $node->getAttribute('parent');
        if (!$nextNode) {
            return null;
        }
        return $this->findClassName($nextNode);
    }
    
    private function readFile(string $filename): string {
        $fh = fopen($filename, 'r');
        $content = '';

        while (!feof($fh)) {
            $content .= fgets($fh);
        }

        fclose($fh);
        return $content;
    }

    private static function getFinder(): NodeFinder
    {
        return static::$nodeFinder ?? static::$nodeFinder = new NodeFinder;
    }
    
    private function createParser(): Parser
    {
        return (new ParserFactory)->createForVersion(PhpVersion::getHostVersion());
    }

    private function getReflector(\Closure $closure): \ReflectionFunction
    {
        return new \ReflectionFunction($closure);
    }

    private function getPrinter(): PrettyPrinterAbstract
    {
        return (new Standard);
    }

    private function createFindCallback(\ReflectionFunction $reflector): callable
    {
        return static function(Node $node) use ($reflector): bool {
            return ($node instanceof ArrowFunction || $node instanceof NodeClosure)
                && $node->getStartLine() == $reflector->getStartLine();
        };
    }

    /**
     * @param $nodes
     * @param callable $filter
     * @return Node
     */
    private static function find($nodes, callable $filter):? Node
    {
        if (!is_array($nodes)) {
            $nodes = [$nodes];
        }

        $visitor = new class($filter) extends FirstFindingVisitor {
            /**
             * @var Node[]
             */
            private $stack = [];
            public function enterNode(Node $node)
            {
                if (!empty($this->stack)) {
                    $node->setAttribute('parent', array_last($this->stack));
                }

                $this->stack[] = $node;

                return parent::enterNode($node);
            }

            public function beforeTraverse(array $nodes):? array
            {
                $this->stack = [];
                return parent::beforeTraverse($nodes);
            }

            public function leaveNode(Node $node)
            {
                array_pop($this->stack);
                return parent::leaveNode($node);
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);

        return $visitor->getFoundNode();
    }

    private function getNamespace(array $ast):? Node\Stmt\Namespace_
    {
        return static::getFinder()->findFirstInstanceOf($ast, Node\Stmt\Namespace_::class);
    }

    private function getUseNodes(array $ast):? array
    {
        $ast = static::getFinder()->find($ast, static fn($node) => $node instanceof Node\Stmt\Use_
            || $node instanceof Node\Stmt\GroupUse
        );

        return $ast == [] ? null : $ast;
    }

    private function getVisitor(?Node\Stmt\Namespace_ $namespace, ?array $uses, callable $clsNameFinder, string $file): NodeVisitorAbstract
    {
        return new class($namespace, $uses, $clsNameFinder, $file) extends NodeVisitorAbstract
        {
            /**
             * @var Node[]
             */
            private $stack = [];
            private $clsNameFinder;
            public function __construct(
                private ?Node\Stmt\Namespace_ $namespace,
                private ?array $uses,
                callable $clsNameFinder,
                private string $file
            ) {
                $this->clsNameFinder = $clsNameFinder;
            }

            public function beforeTraverse(array $nodes)
            {
                $this->stack = [];
            }

            public function enterNode(Node $node)
            {
                static $cls = null;

                if (!empty($this->stack)) {
                    $node->setAttribute('parent', array_last($this->stack));
                }

                $this->stack[] = $node;

                if ($node instanceof Name && !$node instanceof Name\FullyQualified) {
                    foreach ($this->uses ?? [] as $use) {
                        $parts = [];
                        if ($use instanceof Node\Stmt\GroupUse) {
                            $parts = $use->prefix->getParts();
                        }

                        foreach ($use->uses as $useUse) {
                            if ($useUse->name->getLast() == $node->getLast()) {
                                $parts = array_merge($parts, $useUse->name->getParts());
                                return new Name\FullyQualified($parts, $node->getAttributes());
                            }
                        }
                    }

                    if ($node->getAttribute('parent') instanceof Node\Expr\ConstFetch) {
                        if (in_array(strtolower($node->getFirst()), ['null', 'false', 'true'])) {
                            return $node;
                        }
                        foreach (get_defined_constants() as $name => $v) {
                            if ($node->getFirst() == $name) {
                                return new Name\FullyQualified($node->getFirst(), $node->getAttributes());
                            }
                        }
                    }

                    if ($node->getAttribute('parent') instanceof Node\Expr\FuncCall) {
                        $code = strtolower($node->toCodeString());
                        $definedFunctions = get_defined_functions();
                        foreach ($definedFunctions['internal'] as $name) {
                            if ($code == $name) {
                                return new Name\FullyQualified($name, $node->getAttributes());
                            }
                        }

                        foreach ($definedFunctions['user'] as $name) {
                            if ($code == $name) {
                                return new Name\FullyQualified($name, $node->getAttributes());
                            }
                        }
                    }

                    $nodeName = [];
                    $replaceNode = false;
                    foreach ($node->getParts() as $part) {
                        if (strtolower($part) == 'self' || strtolower($part) == 'static') {
                            if (!$cls) $cls = ($this->clsNameFinder)($node);
                            if ($cls) {
                                $part = $cls->name->name;
                                $replaceNode = true;
                            }
                        }
                        $nodeName[] = $part;
                    }

                    if ($replaceNode) {
                        $node = new Name($nodeName, $node->getAttributes());
                    }

                    if ($this->namespace) {
                        return new Name\FullyQualified(
                            [...$this->namespace->name->getParts(), ...$node->getParts()],
                            $node->getAttributes()
                        );
                    } else {
                        return new Name\FullyQualified($node->getParts(), $node->getAttributes());
                    }
                }

                if ($node instanceof Node\Scalar\MagicConst\Class_) {
                    if (!$cls) $cls = ($this->clsNameFinder)($node);
                    if ($cls) {
                        $part = $cls->name->name;
                        return new Node\Expr\ClassConstFetch(new Name\FullyQualified([...$this->namespace->name->getParts(), $part]), 'class',
                            $node->getAttributes()
                        );
                    }

                    return $node;
                }

                if ($node instanceof Node\Scalar\MagicConst\File) {
                    return new Node\Scalar\String_($this->file, $node->getAttributes());
                }


                return $node;
            }

            public function leaveNode(Node $node)
            {
                array_pop($this->stack);
            }
        };
    }
}
