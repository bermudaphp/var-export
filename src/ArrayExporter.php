<?php

namespace Bermuda\VarExport;

/**
 * Array exporter with complete formatting logic.
 *
 * This class handles the export of PHP arrays to their string representation
 * with configurable formatting options including standard and pretty modes,
 * custom indentation, key sorting, and trailing commas.
 */
class ArrayExporter implements ArrayExporterInterface
{
    /**
     * Create new array exporter with specified configuration.
     *
     * @param FormatterConfig $config Formatter configuration options
     */
    public function __construct(
        private FormatterConfig $config = new FormatterConfig()
    ) {}

    /**
     * Export array to its string representation with semicolon.
     *
     * @param array $var The array to export
     * @return string The exported array as PHP code with semicolon
     * @throws ArrayExportException If array cannot be exported
     */
    public function exportArray(array $var): string
    {
        return $this->formatArray($var, $this->config) . ';';
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
     * Format array recursively with specified configuration and depth.
     *
     * @param array $array The array to format
     * @param FormatterConfig $config Configuration options
     * @param int $depth Current recursion depth
     * @return string The formatted array string
     * @throws ArrayExportException If maximum depth exceeded
     */
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

    /**
     * Format array in standard (compact, single-line) mode.
     *
     * @param array $array The array to format
     * @param array $keys Array keys in desired order
     * @param FormatterConfig $config Configuration options
     * @param int $depth Current recursion depth
     * @return string The formatted array string
     */
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

    /**
     * Format array in pretty (multi-line with indentation) mode.
     *
     * @param array $array The array to format
     * @param array $keys Array keys in desired order
     * @param FormatterConfig $config Configuration options
     * @param int $depth Current recursion depth
     * @return string The formatted array string
     */
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

        if ($config->trailingComma && !empty($items)) {
            $lastIndex = array_key_last($items);
            $items[$lastIndex] .= ',';
        }

        return "[\n" . implode(",\n", $items) . "\n$indent]";
    }

    /**
     * Format a single value within an array.
     *
     * @param mixed $value The value to format
     * @param FormatterConfig $config Configuration options
     * @param int $depth Current recursion depth
     * @return string The formatted value string
     * @throws ArrayExportException If value type cannot be exported
     */
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

    /**
     * Format closure value with proper indentation.
     *
     * @param \Closure $closure The closure to format
     * @param FormatterConfig $config Configuration options
     * @param int $depth Current depth for indentation
     * @return string The formatted closure
     */
    private function formatClosureValue(\Closure $closure, FormatterConfig $config, int $depth): string
    {
        try {
            $exporter = new ClosureExporter($config);
            $formattedClosure = $exporter->exportClosureWithDepth($closure, $depth);

            // Fix indentation of closing brace to match array level
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

    /**
     * Format numeric values handling special float cases.
     *
     * @param int|float $value The numeric value to format
     * @return string The formatted numeric string
     */
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

    /**
     * Escape a string for safe inclusion in PHP code.
     *
     * @param string $str The string to escape
     * @return string The escaped string wrapped in single quotes
     */
    private function escapeString(string $str): string
    {
        return "'" . addcslashes($str, "'\\") . "'";
    }

    /**
     * Sort array keys with numeric keys first, then string keys alphabetically.
     *
     * @param array $array The array whose keys to sort
     * @return array The sorted keys
     */
    private function getSortedKeys(array $array): array
    {
        $keys = array_keys($array);
        usort($keys, function($a, $b) {
            // Numeric keys first, then string keys
            if (is_int($a) && is_string($b)) return -1;
            if (is_string($a) && is_int($b)) return 1;
            return $a <=> $b;
        });
        return $keys;
    }
}