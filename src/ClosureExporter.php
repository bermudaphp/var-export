<?php

namespace Bermuda\VarExport;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as NodeClosure;
use PhpParser\Node\Name;

class ClosureExporter implements ClosureExporterInterface
{
    protected static NodeFinder|null $nodeFinder = null;
    public function exportClosure(\Closure $closure): string
    {
        $code = $this->readFile(($reflector = new \ReflectionFunction($closure))
            ->getFileName());
        $ast = $this->createParser()->parse($code);

        $node = static::getFinder()->findFirst($ast, $this->createFindCallback($reflector));

        $traverser = new NodeTraverser;
        $traverser->addVisitor($this->getVisitor($this->getNamespace($ast), $this->getUseNodes($ast)));
        $traverser->traverse([$node]);

        return $this->getPrinter()->prettyPrintExpr($node);
    }
    
    protected function readFile(string $filename): string {
        $fh = fopen($filename, 'r');
        $content = '';

        while (!feof($fh)) {
            $content .= fgets($fh);
        }

        fclose($fh);
        return $content;
    }
    
    protected function createParser(): Parser
    {
        return (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    }

    protected function getReflector(\Closure $closure): \ReflectionFunction
    {
        return new \ReflectionFunction($closure);
    }

    protected function getPrinter(): PrettyPrinterAbstract
    {
        return (new Standard);
    }

    protected function createFindCallback(\ReflectionFunction $reflector): callable
    {
        return static function(Node $node) use ($reflector): bool {
            return ($node instanceof ArrowFunction || $node instanceof NodeClosure)
                && $node->getStartLine() == $reflector->getStartLine();
        };
    }

    protected static function getFinder(): NodeFinder
    {
        return static::$nodeFinder ?? static::$nodeFinder = new NodeFinder;
    }

    protected function getNamespace(array $ast):? Node\Stmt\Namespace_
    {
        return static::getFinder()->findFirstInstanceOf($ast, Node\Stmt\Namespace_::class);
    }

    protected function getUseNodes(array $ast):? array
    {
        $ast = static::getFinder()->find($ast, static fn($node) => $node instanceof Node\Stmt\Use_
            || $node instanceof Node\Stmt\GroupUse
        );

        return $ast == [] ? null : $ast;
    }

    protected function getVisitor(?Node\Stmt\Namespace_ $namespace, ?array $uses)
    {
        return new class($namespace, $uses) extends NodeVisitorAbstract
        {
            /**
             * @var Node[]
             */
            private $stack = [];
            public function __construct(
                private ?Node\Stmt\Namespace_ $namespace,
                private ?array $uses) {
            }

            public function beforeTraverse(array $nodes)
            {
                $this->stack = [];
            }

            public function enterNode(Node $node)
            {
                if (!empty($this->stack)) {
                    $node->setAttribute('parent', $this->stack[count($this->stack) - 1]);
                }

                $this->stack[] = $node;

                if ($node instanceof Name && !$node instanceof Name\FullyQualified) {
                    foreach ($this->uses as $use) {
                        $parts = [];
                        if ($use instanceof Node\Stmt\GroupUse) {
                            $parts = $use->prefix->parts;
                        }

                        foreach ($use->uses as $useUse) {
                            if (end($useUse->name->parts) == end($node->parts)) {
                                $parts = array_merge($parts, $useUse->name->parts);
                                return new Name\FullyQualified($parts, $node->getAttributes());
                            }
                        }
                    }

                    if ($node->getAttribute('parent') instanceof Node\Expr\ConstFetch) {
                        if (in_array(strtolower($node->parts[0]), ['null', 'false', 'true'])) {
                            return $node;
                        }
                        foreach (get_defined_constants() as $name => $v) {
                            if ($node->parts[0] == $name) {
                                return new Name\FullyQualified($node->parts[0], $node->getAttributes());
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

                    if ($this->namespace) {
                        return new Name\FullyQualified(
                            [...$this->namespace->name->parts, ...$node->parts],
                            $node->getAttributes()
                        );
                    }
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
