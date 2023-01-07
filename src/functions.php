<?php

namespace Bermuda\VarExporter;

/**
 * @param \Closure $closure
 * @return string
 */
function export_closure(\Closure $closure): string
{
    return (new ClosureExporter)->exportClosure($closure);
}

/**
 * @param array $var
 * @return string
 * @throws ArrayExportException
 */
function export_array(array $var): string
{
    return (new ArrayExporter)->exportArray($var);
}
