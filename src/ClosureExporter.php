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
    public function exportClosure(\Closure $closure): string
    {
        $reflector = $this->getReflector($closure);
        $parser = $this->createParser();

        $code = $this->readFile($reflector->getFileName());
        $ast = $parser->parse($code);

        $nodeFinder = new NodeFinder;
        $node = $nodeFinder->findFirst($ast, $this->createFindCallback($reflector));

        if ($reflector->getReturnType()) {
            $node->returnType = new Name($reflector->getReturnType()->getName());
        }

        if ($reflector->getParameters() != []) {
            foreach ($reflector->getParameters() as $pos => $parameter) {
                if ($parameter->getType() instanceof \ReflectionNamedType) {
                    $node->params[$pos]->type->parts = explode('\\', $parameter->getType()->getName());
                }
            }
        }

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
}
