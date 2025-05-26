<?php

namespace Bermuda\VarExport\Formatter;

use Bermuda\VarExport\ClosureExporter;

/**
 * ClosureFormatter with proper body indentation.
 */
class ClosureFormatter implements FormatterInterface
{
    private ClosureExporter $exporter;

    public function __construct()
    {
        $this->exporter = new ClosureExporter();
    }

    public function format(mixed $value, FormatterConfig $config): string
    {
        return $this->formatWithDepth($value, $config, 0);
    }

    public function formatWithDepth(mixed $value, FormatterConfig $config, int $depth): string
    {
        if (!$value instanceof \Closure) {
            throw new ExportException('Value must be a Closure', $value);
        }

        $code = $this->exporter->exportClosure($value);

        return $config->mode === FormatterMode::PRETTY
            ? $this->formatPretty($code, $config, $depth)
            : $this->formatStandard($code);
    }

    private function formatStandard(string $code): string
    {
        return preg_replace('/\s+/', ' ', trim($code));
    }

    private function formatPretty(string $code, FormatterConfig $config, int $depth): string
    {
        $lines = explode("\n", $code);
        $result = [];
        $baseIndent = str_repeat($config->indent, $depth + 1);

        $braceLevel = 0;
        $inFunction = false;

        foreach ($lines as $i => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                $result[] = '';
                continue;
            }

            if ($i === 0) {
                // First line - function declaration
                $result[] = $trimmedLine;
                $braceLevel += substr_count($trimmedLine, '{') - substr_count($trimmedLine, '}');
                $inFunction = true;
            } else {
                // Body lines - calculate proper indentation based on brace nesting
                $currentBraceLevel = $braceLevel;

                // Adjust level for closing braces on current line
                $closingBraces = substr_count($trimmedLine, '}');
                if ($closingBraces > 0) {
                    $currentBraceLevel = max(0, $braceLevel - $closingBraces);
                }

                // Add indentation: base + additional for each brace level
                $indent = $baseIndent . str_repeat($config->indent, $currentBraceLevel);
                $result[] = $indent . $trimmedLine;

                // Update brace level for next iteration
                $braceLevel += substr_count($trimmedLine, '{') - substr_count($trimmedLine, '}');
                $braceLevel = max(0, $braceLevel);
            }
        }

        return implode("\n", $result);
    }
}
