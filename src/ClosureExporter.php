<?php

namespace Bermuda\VarExport;

/**
 * Closure exporter with complete formatting logic.
 *
 * This class handles the export of PHP closures to their string representation
 * using PHP-Parser for AST analysis and proper namespace resolution.
 * Supports both standard and pretty formatting modes.
 */
class ClosureExporter implements ClosureExporterInterface
{
    /** @var \PhpParser\NodeFinder|null Cached node finder instance */
    private static ?\PhpParser\NodeFinder $nodeFinder = null;

    /**
     * Create new closure exporter with specified configuration.
     *
     * @param FormatterConfig $config Formatter configuration options
     */
    public function __construct(
        private FormatterConfig $config = new FormatterConfig()
    ) {}

    /**
     * Extract and export closure source code with namespace resolution.
     *
     * @param \Closure $closure The closure to export
     * @return string The exported closure source code
     * @throws ExportException If closure cannot be exported
     */
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

    /**
     * Read file content safely with error handling.
     *
     * @param string $filename The file to read
     * @return string The file content
     * @throws ExportException If file cannot be read
     */
    private function readFile(string $filename): string
    {
        $content = file_get_contents($filename);
        if ($content === false) {
            throw new ExportException("Cannot read file: $filename");
        }
        return $content;
    }

    /**
     * Create PHP parser for the current PHP version.
     *
     * @return \PhpParser\Parser The parser instance
     */
    private function createParser(): \PhpParser\Parser
    {
        return (new \PhpParser\ParserFactory())->createForVersion(
            \PhpParser\PhpVersion::getHostVersion()
        );
    }

    /**
     * Find the closure node in AST matching the reflection information.
     *
     * @param array $ast The parsed AST
     * @param \ReflectionFunction $reflector The closure reflection
     * @return \PhpParser\Node|null The found closure node
     */
    private function findClosureNode(array $ast, \ReflectionFunction $reflector): ?\PhpParser\Node
    {
        $finder = $this->getNodeFinder();
        return $finder->findFirst($ast, function(\PhpParser\Node $node) use ($reflector) {
            return ($node instanceof \PhpParser\Node\Expr\Closure ||
                    $node instanceof \PhpParser\Node\Expr\ArrowFunction) &&
                $node->getStartLine() === $reflector->getStartLine();
        });
    }

    /**
     * Get or create a cached node finder instance.
     *
     * @return \PhpParser\NodeFinder The node finder
     */
    private function getNodeFinder(): \PhpParser\NodeFinder
    {
        return self::$nodeFinder ??= new \PhpParser\NodeFinder();
    }

    /**
     * Create node visitor for namespace and import resolution.
     *
     * @param array $ast The parsed AST
     * @param string $filename The source filename
     * @return \PhpParser\NodeVisitorAbstract The node visitor
     */
    private function createNodeVisitor(array $ast, string $filename): \PhpParser\NodeVisitorAbstract
    {
        $finder = $this->getNodeFinder();
        $namespace = $finder->findFirstInstanceOf($ast, \PhpParser\Node\Stmt\Namespace_::class);
        $uses = $this->getUseStatements($ast);

        return new class($namespace, $uses, $filename) extends \PhpParser\NodeVisitorAbstract {
            /**
             * Create new node visitor for name resolution.
             *
             * @param \PhpParser\Node\Stmt\Namespace_|null $namespace The namespace node
             * @param array|null $uses The use statement nodes
             * @param string $filename The source filename
             */
            public function __construct(
                private ?\PhpParser\Node\Stmt\Namespace_ $namespace,
                private ?array $uses,
                private string $filename
            ) {}

            /**
             * Process nodes during AST traversal for name resolution.
             *
             * @param \PhpParser\Node $node The current node
             * @return \PhpParser\Node|null The replacement node or null
             */
            public function enterNode(\PhpParser\Node $node): ?\PhpParser\Node
            {
                if ($node instanceof \PhpParser\Node\Name && !$node instanceof \PhpParser\Node\Name\FullyQualified) {
                    return $this->resolveNameNode($node);
                }

                if ($node instanceof \PhpParser\Node\Scalar\MagicConst) {
                    return $this->replaceMagicConst($node);
                }

                return null;
            }

            /**
             * Replace magic constants with their actual values.
             *
             * @param \PhpParser\Node\Scalar\MagicConst $node The magic constant node
             * @return \PhpParser\Node The replacement node
             */
            private function replaceMagicConst(\PhpParser\Node\Scalar\MagicConst $node): \PhpParser\Node
            {
                return match (true) {
                    $node instanceof \PhpParser\Node\Scalar\MagicConst\File =>
                    new \PhpParser\Node\Scalar\String_($this->filename),
                    $node instanceof \PhpParser\Node\Scalar\MagicConst\Dir =>
                    new \PhpParser\Node\Scalar\String_(dirname($this->filename)),
                    $node instanceof \PhpParser\Node\Scalar\MagicConst\Namespace_ =>
                    new \PhpParser\Node\Scalar\String_($this->namespace?->name->toString() ?? ''),
                    default => $node,
                };
            }

            /**
             * Resolve name nodes to fully qualified names.
             *
             * @param \PhpParser\Node\Name $node The name node to resolve
             * @return \PhpParser\Node The resolved node
             */
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

    /**
     * Extract use statements from the AST.
     *
     * @param array $ast The parsed AST
     * @return array|null The use statement nodes
     */
    private function getUseStatements(array $ast): ?array
    {
        $finder = $this->getNodeFinder();
        $uses = $finder->find($ast, static function(\PhpParser\Node $node) {
            return $node instanceof \PhpParser\Node\Stmt\Use_ ||
                $node instanceof \PhpParser\Node\Stmt\GroupUse;
        });

        return empty($uses) ? null : $uses;
    }

    /**
     * Export closure to its string representation.
     *
     * @param \Closure $closure The closure to export
     * @return string The exported closure as a string
     * @throws ExportException If closure cannot be exported
     */
    public function exportClosure(\Closure $closure): string
    {
        return $this->exportClosureWithDepth($closure, 0);
    }

    /**
     * Export closure with specific depth for proper indentation.
     *
     * @param \Closure $closure The closure to export
     * @param int $depth Current depth for indentation
     * @return string The exported closure as a string
     * @throws ExportException If closure cannot be exported
     */
    public function exportClosureWithDepth(\Closure $closure, int $depth): string
    {
        if ($this->config->mode === FormatterMode::STANDARD) {
            return $this->formatStandard($closure);
        }

        return $this->formatPretty($closure, $depth);
    }

    /**
     * Create new exporter instance with different configuration.
     *
     * @param FormatterConfig $config The new configuration
     * @return self A new exporter instance
     */
    public function withConfig(FormatterConfig $config): self
    {
        return new self($config);
    }

    /**
     * Format closure in standard (compact) mode.
     *
     * @param \Closure $closure The closure to format
     * @return string The formatted closure
     */
    private function formatStandard(\Closure $closure): string
    {
        try {
            $code = $this->doExportClosure($closure);
            return preg_replace('/\s+/', ' ', trim($code));
        } catch (\Exception $e) {
            return $this->createClosureDescription($closure);
        }
    }

    /**
     * Format closure in pretty mode with proper indentation.
     *
     * @param \Closure $closure The closure to format
     * @param int $depth Current depth for base indentation
     * @return string The formatted closure
     */
    private function formatPretty(\Closure $closure, int $depth): string
    {
        try {
            $code = $this->doExportClosure($closure);
            return $this->applyPrettyFormatting($code, $depth);
        } catch (\Exception $e) {
            return $this->createClosureDescription($closure);
        }
    }

    /**
     * Apply pretty formatting to closure code with proper indentation.
     *
     * @param string $code The closure source code
     * @param int $depth Current depth for base indentation
     * @return string The formatted code
     */
    private function applyPrettyFormatting(string $code, int $depth): string
    {
        $lines = explode("\n", $code);
        $result = [];
        $baseIndentLevel = $depth + 1;
        $currentBraceLevel = 0;

        foreach ($lines as $i => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                $result[] = '';
                continue;
            }

            if ($i === 0) {
                // First line - function declaration, no additional indentation
                $result[] = $trimmedLine;
                $currentBraceLevel += substr_count($trimmedLine, '{') - substr_count($trimmedLine, '}');
            } else {
                // Body lines - calculate proper indentation based on brace nesting
                $openBraces = substr_count($trimmedLine, '{');
                $closeBraces = substr_count($trimmedLine, '}');

                // For lines that start with closing brace, reduce indent first
                if (preg_match('/^\s*}/', $trimmedLine)) {
                    $currentBraceLevel = max(0, $currentBraceLevel - 1);
                }

                // Apply indentation
                $indentLevel = $baseIndentLevel + $currentBraceLevel;
                $indent = str_repeat($this->config->indent, $indentLevel);
                $result[] = $indent . $trimmedLine;

                // Update brace level for next lines
                if (!preg_match('/^\s*}/', $trimmedLine)) {
                    $currentBraceLevel += $openBraces - $closeBraces;
                }
                $currentBraceLevel = max(0, $currentBraceLevel);
            }
        }

        return implode("\n", $result);
    }

    /**
     * Create a safe description of a closure when export fails.
     *
     * @param \Closure $closure The closure to describe
     * @return string A safe description
     */
    private function createClosureDescription(\Closure $closure): string
    {
        try {
            $reflection = new \ReflectionFunction($closure);
            $params = array_map(
                fn($param) => '$' . $param->getName(),
                $reflection->getParameters()
            );

            return sprintf(
                'function(%s) { /* closure */ }',
                implode(', ', $params)
            );
        } catch (\Exception) {
            return 'function() { /* closure */ }';
        }
    }
}