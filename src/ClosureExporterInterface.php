<?php

namespace Bermuda\VarExport;

interface ClosureExporterInterface
{
    public function exportClosure(\Closure $closure): string ;
}
