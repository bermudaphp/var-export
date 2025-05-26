<?php

namespace Bermuda\VarExport;

use Bermuda\VarExport\Formatter\FormatterConfig;

class ClosureExporter implements ClosureExporterInterface
{
    use ClosureExport;

    public function __construct(
        private FormatterConfig $config = new FormatterConfig
    ) {}

    public function exportClosure(\Closure $closure): string
    {
        return $this->doExportClosure($closure);
    }

    public function withConfig(FormatterConfig $config): self
    {
        return new self($config);
    }
}
