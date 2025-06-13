<?php

namespace Bermuda\VarExport;

interface ClosureExporterInterface
{
    /**
     * Export closure to its string representation.
     *
     * @param \Closure $closure The closure to export
     * @return string The exported closure as a string
     * @throws ExportException If closure cannot be exported
     */
    public function exportClosure(\Closure $closure): string ;
}
