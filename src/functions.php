<?php

namespace Bermuda\Utils;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction as A;
use PhpParser\Node\Expr\Closure as C;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

function closureToString(\Closure $closure): string
{
    $reflector = new \ReflectionFunction($closure);

    $reader = static function(string $filename, int $start = null, int $end = null): array {
        $fh = fopen($filename, 'r');
        $num = 0;
        $lines = [];

        while (!feof($fh)) {
            $line = fgets($fh);
            $num++;

            if ($start != null) {
                if ($num >= $start) {
                    $lines[$num] = $line;
                }

                if ($end != null && $end == $num) {
                    break;
                }

                continue;
            }

            $lines[$num] = $line;

            if ($end != null && $end == $num) {
                break;
            }
        }

        fclose($fh);

        return $lines;
    };

    $code = $reader($reflector->getFileName(), $reflector->getStartLine(), $reflector->getEndLine());
    $code = implode('', $code);

    if (!str_starts_with($code, '<?') && !str_starts_with($code, '<?php')) {
        $code = '<?php' . $code;
    }

    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

    $ast = $parser->parse($code);

    $nodeFinder = new NodeFinder();
    
    $node = $nodeFinder->findFirst($ast,
        static fn(Node $node) => $node instanceof A || $node instanceof C
    );

    return (new Standard)->prettyPrintExpr($node);
}
