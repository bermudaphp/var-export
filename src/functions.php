<?php

namespace Bermuda\VarExport;

/**
 * Export a closure to its string representation
 */
function export_closure(\Closure $closure, ?FormatterConfig $config = null, bool $pretty = true): string
{
    return $pretty ? VarExporter::exportPretty($var, $config) : VarExporter::export($var, $config);
}

/**
 * Export an array to its string representation
 */
function export_array(array $var, ?FormatterConfig $config = null, bool $pretty = true): string
{
    return $pretty ? VarExporter::exportPretty($var, $config) : VarExporter::export($var, $config);
}

/**
 * Export any variable to its string representation
 */
function export_var(mixed $var, ?FormatterConfig $config = null, bool $pretty = true): string
{
    return $pretty ? VarExporter::exportPretty($var, $config) : VarExporter::export($var, $config);
}

/**
 * Export any variable with pretty formatting
 */
function export_var_pretty(mixed $var, ?FormatterConfig $config = null): string
{
    return VarExporter::exportPretty($var, $config);
}
