<?php

namespace Bermuda\VarExport;

/**
 * Updated ClosureExport trait with better formatting.
 */
trait ClosureExport
{
    private static ?\PhpParser\NodeFinder $nodeFinder = null;

    private function doExportClosure(\Closure $closure): string
    {
        try {
            $reflector = new \ReflectionFunction($closure);
            $filename = $reflector->getFileName();

            if (!$filename || !file_exists($filename)) {
                throw new ExportException("Cannot locate closure source file", $closure);
            }

            $code = $this->readFile($filename);
            $parser = $this->createParser();
            $ast = $parser->parse($code);

            if (!$ast) {
                throw new ExportException("Failed to parse source file", $closure);
            }

            $node = $this->findClosureNode($ast, $reflector);
            if (!$node) {
                throw new ExportException("Cannot locate closure in source", $closure);
            }

            $visitor = $this->createNodeVisitor($ast, $filename);
            $traverser = new \PhpParser\NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse([$node]);

            $printer = new \PhpParser\PrettyPrinter\Standard();
            return $printer->prettyPrintExpr($node);

        } catch (\Throwable $e) {
            throw new ExportException(
                "Failed to export closure: " . $e->getMessage(),
                $closure
            );
        }
    }

    private function readFile(string $filename): string
    {
        $content = file_get_contents($filename);
        if ($content === false) {
            throw new ExportException("Cannot read file: $filename");
        }
        return $content;
    }

    private function createParser(): \PhpParser\Parser
    {
        return (new \PhpParser\ParserFactory())->createForVersion(
            \PhpParser\PhpVersion::getHostVersion()
        );
    }

    private function findClosureNode(array $ast, \ReflectionFunction $reflector): ?\PhpParser\Node
    {
        $finder = $this->getNodeFinder();
        return $finder->findFirst($ast, function(\PhpParser\Node $node) use ($reflector) {
            return ($node instanceof \PhpParser\Node\Expr\Closure ||
                    $node instanceof \PhpParser\Node\Expr\ArrowFunction) &&
                $node->getStartLine() === $reflector->getStartLine();
        });
    }

    private function getNodeFinder(): \PhpParser\NodeFinder
    {
        return self::$nodeFinder ??= new \PhpParser\NodeFinder();
    }

    private function createNodeVisitor(array $ast, string $filename): \PhpParser\NodeVisitorAbstract
    {
        $finder = $this->getNodeFinder();
        $namespace = $finder->findFirstInstanceOf($ast, \PhpParser\Node\Stmt\Namespace_::class);
        $uses = $this->getUseStatements($ast);

        return new class($namespace, $uses, $filename) extends \PhpParser\NodeVisitorAbstract {
            public function __construct(
                private ?\PhpParser\Node\Stmt\Namespace_ $namespace,
                private ?array $uses,
                private string $filename
            ) {}

            public function enterNode(\PhpParser\Node $node): ?\PhpParser\Node
            {
                if ($node instanceof \PhpParser\Node\Name && !$node instanceof \PhpParser\Node\Name\FullyQualified) {
                    return $this->resolveNameNode($node);
                }

                if ($node instanceof \PhpParser\Node\Scalar\MagicConst\File) {
                    return new \PhpParser\Node\Scalar\String_($this->filename, $node->getAttributes());
                }

                return null;
            }

            private function resolveNameNode(\PhpParser\Node\Name $node): \PhpParser\Node
            {
                $firstName = $node->getFirst();

                // Resolve use statements first
                if ($this->uses) {
                    foreach ($this->uses as $use) {
                        if ($use instanceof \PhpParser\Node\Stmt\Use_) {
                            foreach ($use->uses as $useUse) {
                                if ($useUse->name->getLast() === $firstName ||
                                    ($useUse->alias && $useUse->alias->name === $firstName)) {
                                    $parts = $useUse->name->getParts();
                                    if (count($node->getParts()) > 1) {
                                        $remaining = array_slice($node->getParts(), 1);
                                        $parts = array_merge($parts, $remaining);
                                    }
                                    return new \PhpParser\Node\Name\FullyQualified(
                                        $parts,
                                        $node->getAttributes()
                                    );
                                }
                            }
                        }

                        if ($use instanceof \PhpParser\Node\Stmt\GroupUse) {
                            $prefix = $use->prefix->getParts();
                            foreach ($use->uses as $useUse) {
                                if ($useUse->name->getLast() === $firstName ||
                                    ($useUse->alias && $useUse->alias->name === $firstName)) {
                                    $parts = array_merge($prefix, $useUse->name->getParts());
                                    if (count($node->getParts()) > 1) {
                                        $remaining = array_slice($node->getParts(), 1);
                                        $parts = array_merge($parts, $remaining);
                                    }
                                    return new \PhpParser\Node\Name\FullyQualified(
                                        $parts,
                                        $node->getAttributes()
                                    );
                                }
                            }
                        }
                    }
                }

                // Add namespace prefix if exists
                if ($this->namespace && !str_contains($firstName, '\\')) {
                    return new \PhpParser\Node\Name\FullyQualified(
                        array_merge($this->namespace->name->getParts(), $node->getParts()),
                        $node->getAttributes()
                    );
                }

                return new \PhpParser\Node\Name\FullyQualified($node->getParts(), $node->getAttributes());
            }
        };
    }

    private function getUseStatements(array $ast): ?array
    {
        $finder = $this->getNodeFinder();
        $uses = $finder->find($ast, function(\PhpParser\Node $node) {
            return $node instanceof \PhpParser\Node\Stmt\Use_ ||
                $node instanceof \PhpParser\Node\Stmt\GroupUse;
        });

        return empty($uses) ? null : $uses;
    }
}