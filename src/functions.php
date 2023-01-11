<?php

namespace Bermuda\VarExport;

/**
 * @param \Closure $closure
 * @return string
 */
function export_closure(\Closure $closure): string
{
    return VarExporter::export($closure);
}

/**
 * @param array $var
 * @return string
 * @throws ArrayExportException
 */
function export_array(array $var): string
{
    return VarExporter::export($var);
}

/**
 * @param mixed $var
 * @return string
 * @throws ExportException
 */
function export_var(mixed $var): string
{
    return VarExporter::export($var);
}
