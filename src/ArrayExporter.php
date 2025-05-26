<?php

namespace Bermuda\VarExport;

use Bermuda\VarExport\Formatter\ArrayFormatter;
use Bermuda\VarExport\Formatter\FormatterConfig;

class ArrayExporter implements ArrayExporterInterface
{
    private ArrayFormatter $formatter;

    public function __construct(
        private FormatterConfig $config = new FormatterConfig
    ) {
        $this->formatter = new ArrayFormatter;
    }

    public function exportArray(array $var): string
    {
        return $this->formatter->format($var, $this->config) . ';';
    }

    public function withConfig(FormatterConfig $config): self
    {
        return new self($config);
    }
}
