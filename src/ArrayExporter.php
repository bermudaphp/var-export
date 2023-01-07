<?php

namespace Bermuda\VarExport

class ArrayExporter 
{
    protected $whitespace = '    ';
    public function exportArray(array $var): string
    {
        return $this->doExport($var) . ';';
    }

    protected function doExport(array $var, int $deep = 1): string
    {
        $content = "[" . PHP_EOL;
        $content .= $this->addWhitespace($deep);

        foreach ($var as $key => $value) {

            $k = $key;
            if (!is_int($key)) {
                $key = "'$key'";
            }

            if (is_array($value)) {
                $value = $this->doExport($value, $deep + 1);
                $content .= "$key => $value";
            } elseif ($value instanceof \Closure) {
                $value = export_closure($value);
                $content .= "$key => $value";
            } else {
                $content .= "$key => '$value'";
            }

            $content .= ',';
            $content .= PHP_EOL;

            if (array_key_last($var) == $k) {
                $deep--;
            }

            $content .= $this->addWhitespace($deep);
        }

        return $content .= ']';
    }

    protected function addWhitespace(int $deep): string
    {
        $str = '';
        while ($deep--) {
            $str .= $this->whitespace;
        }

        return $str;
    }
}
