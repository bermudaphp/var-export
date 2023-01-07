<?php

namespace Bermuda\VarExport

interface ArrayExporterInterface
{
    public function exportArray(array $var): string ;
}
