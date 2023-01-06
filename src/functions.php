<?php

namespace Bermuda\Utils;

function closureToString(\Closure $closure): string
{
    $str = '';
    $reflector = new \ReflectionFunction($closure);

    if ($reflector->isStatic()) {
        $str .= 'static ';
    }

    $str .= 'function';

    if ($reflector->returnsReference()) {
        $str .= ' & ';
    }

    $str .= '(';

    $params = $reflector->getParameters();
    $implodeType = function(\ReflectionType $type) {
        $str = '';
        if ($type instanceof \ReflectionNamedType) {
            if ($type->allowsNull()) {
                $str .= '?';
            }
            $str .= $type->getName();
        } elseif ($type instanceof \ReflectionUnionType) {
            $glue = '';
            foreach ($type->getTypes() as $t) {
                $str .= $glue;
                $str .= $t->getName();
                $glue = '|';
            }
        } else {
            $glue = '';
            $str = '';
            foreach ($type->getTypes() as $t) {
                $str .= $glue;
                $str .= $t->getName();
                $glue = '&';
            }
        }

        return $str;
    };

    if ($params != []) {
        $glue = '';
        foreach ($params as $param) {
            $str .= $glue;

            if ($param->hasType()) {
                $str .= $implodeType($param->getType());
                $str .= ' ';
            }

            if ($param->isVariadic()) {
                $str .= ' ... ';
            }

            if ($param->isPassedByReference()) {
                $str .= '&';
            }

            $str .= '$';
            $str .= $param->getName();

            if ($param->isDefaultValueAvailable()) {

                $str .= ' = ';
                $str .= $param->getDefaultValue() === null ? 'null'
                    : $param->getDefaultValue();
            }

            $glue = ', ';
        }

    }

    $str .= ')';

    if ($reflector->getClosureUsedVariables() != []) {
        $str .= ' use (';
        $str .= implode(',', array_map(static fn($v) => "$$v", array_keys($reflector->getClosureUsedVariables())));
        $str .= ')';
    }

    if ($reflector->getReturnType()) {
        $str .= ': ';
        $str .= $implodeType($reflector->getReturnType());
    }

    $str .= ' {';
    $readLines = static function(string $filename, int $start = null, int $end = null): array {
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

    $body = $readLines($reflector->getFileName(), $reflector->getStartLine(), $reflector->getEndLine());

    $lastKey = array_key_last($body);

    $body = (function () use ($body, $lastKey) {
        $str = '';
        foreach ($body as $num => $line) {
            $str .= PHP_EOL;
            if ($lastKey == $num) {
                $str .= trim($line);
                break;
            }

            $str .= '    ';
            $str .= trim($line);
        }

        return $str;
    })();

    $start = stripos($body, '{');
    $end = strripos($body, '}');

    $str .= substr($body, $start + 1, $end - $start - 1);

    $str .= '}';

    return $str;
}
