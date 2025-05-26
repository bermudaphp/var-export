<?php

namespace Bermuda\VarExport\Formatter;

use Bermuda\VarExport\ArrayExportException;

/**
 * ArrayFormatter with properly indented closure bodies.
 */
class ArrayFormatter implements FormatterInterface
{
    public function format(mixed $value, FormatterConfig $config): string
    {
        if (!is_array($value)) {
            throw new ArrayExportException('Value must be an array', $value);
        }

        return $this->formatArray($value, $config);
    }

    private function formatArray(array $array, FormatterConfig $config, int $depth = 0): string
    {
        if ($depth > $config->maxDepth) {
            throw new ArrayExportException("Maximum depth of {$config->maxDepth} exceeded");
        }

        if (empty($array)) {
            return '[]';
        }

        $keys = $config->sortKeys ? $this->getSortedKeys($array) : array_keys($array);

        return $config->mode === FormatterMode::PRETTY
            ? $this->formatPretty($array, $keys, $config, $depth)
            : $this->formatStandard($array, $keys, $config, $depth);
    }

    private function formatStandard(array $array, array $keys, FormatterConfig $config, int $depth): string
    {
        $items = [];
        foreach ($keys as $key) {
            $value = $array[$key];
            $formattedKey = is_int($key) ? $key : $this->escapeString((string)$key);
            $formattedValue = $this->formatValue($value, $config, $depth + 1);
            $items[] = "$formattedKey => $formattedValue";
        }

        return '[' . implode(', ', $items) . ']';
    }

    private function formatPretty(array $array, array $keys, FormatterConfig $config, int $depth): string
    {
        $indent = str_repeat($config->indent, $depth);
        $nextIndent = str_repeat($config->indent, $depth + 1);

        $items = [];
        foreach ($keys as $key) {
            $value = $array[$key];
            $formattedKey = is_int($key) ? $key : $this->escapeString((string)$key);
            $formattedValue = $this->formatValue($value, $config, $depth + 1);

            $items[] = $nextIndent . $formattedKey . ' => ' . $formattedValue;
        }

        $lastItem = array_key_last($items);
        if ($config->trailingComma && $lastItem !== null) {
            $items[$lastItem] .= ',';
        }

        return "[\n" . implode(",\n", $items) . "\n$indent]";
    }

    private function formatValue(mixed $value, FormatterConfig $config, int $depth): string
    {
        return match (true) {
            is_array($value) => $this->formatArray($value, $config, $depth),
            $value instanceof \Closure => $this->formatClosureValue($value, $config, $depth),
            is_int($value), is_float($value) => $this->formatNumeric($value),
            is_string($value) => $this->escapeString($value),
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_object($value) => throw new ArrayExportException(
                "Object of type " . get_class($value) . " cannot be exported at depth $depth",
                $value
            ),
            is_resource($value) => throw new ArrayExportException(
                "Resource cannot be exported at depth $depth"
            ),
            default => throw new ArrayExportException(
                "Unsupported type " . gettype($value) . " at depth $depth",
                $value
            )
        };
    }

    private function formatClosureValue(\Closure $closure, FormatterConfig $config, int $depth): string
    {
        try {
            $formatter = new ClosureFormatter();
            $formattedClosure = $formatter->formatWithDepth($closure, $config, $depth);
            
            $lines = explode("\n", $formattedClosure);
            if (count($lines) > 1) {
                $lastLine = array_pop($lines);
                $lines[] = preg_replace('/^\s+/', str_repeat($config->indent, $depth), $lastLine);
            }
            return implode("\n", $lines);
        } catch (\Exception $e) {
            return $this->createClosureDescription($closure);
        }
    }

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

    private function formatNumeric(int|float $value): string
    {
        if (is_float($value)) {
            if (is_infinite($value)) {
                return $value > 0 ? 'INF' : '-INF';
            }
            if (is_nan($value)) {
                return 'NAN';
            }
        }

        return (string)$value;
    }

    private function escapeString(string $str): string
    {
        return "'" . addcslashes($str, "'\\") . "'";
    }

    private function getSortedKeys(array $array): array
    {
        $keys = array_keys($array);
        usort($keys, function($a, $b) {
            if (is_int($a) && is_string($b)) return -1;
            if (is_string($a) && is_int($b)) return 1;
            return $a <=> $b;
        });
        return $keys;
    }
}
