<?php

namespace Bermuda\VarExport;

interface ArrayExporterInterface
{
    /**
     * Export array to its string representation with semicolon.
     *
     * @param array $var The array to export
     * @return string The exported array as PHP code with semicolon
     * @throws ArrayExportException If array cannot be exported
     */
    public function exportArray(array $var): string ;
}
