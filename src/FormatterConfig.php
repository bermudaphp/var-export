<?php

namespace Bermuda\VarExport;

/**
 * Updated FormatterConfig without circular reference detection.
 */
readonly class FormatterConfig
{
    public function __construct(
        public FormatterMode $mode = FormatterMode::STANDARD,
        public string $indent = '    ',
        public int $maxDepth = 100,
        public bool $sortKeys = false,
        public bool $trailingComma = false
    ) {}

    public function withMode(FormatterMode $mode): self
    {
        return new self($mode, $this->indent, $this->maxDepth, $this->sortKeys, $this->trailingComma);
    }

    public function withIndent(string $indent): self
    {
        return new self($this->mode, $indent, $this->maxDepth, $this->sortKeys, $this->trailingComma);
    }

    public function withMaxDepth(int $maxDepth): self
    {
        return new self($this->mode, $this->indent, $maxDepth, $this->sortKeys, $this->trailingComma);
    }

    public function withSortKeys(bool $sortKeys): self
    {
        return new self($this->mode, $this->indent, $this->maxDepth, $sortKeys, $this->trailingComma);
    }

    public function withTrailingComma(bool $trailingComma): self
    {
        return new self($this->mode, $this->indent, $this->maxDepth, $this->sortKeys, $trailingComma);
    }
}