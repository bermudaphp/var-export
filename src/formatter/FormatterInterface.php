<?php

namespace Bermuda\VarExport\Formatter;

interface FormatterInterface
{
    public function format(mixed $value, FormatterConfig $config): string;
}