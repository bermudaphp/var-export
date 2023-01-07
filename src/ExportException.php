<?php

namespace Bermuda\VarExport;

class ExportException extends \Exception
{
    public function __construct(string $msg, public readonly mixed $var)
    {
        parent::__construct($msg);
    }
}