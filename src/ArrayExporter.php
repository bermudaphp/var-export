<?php

namespace Bermuda\VarExport;

class ArrayExporter implements ArrayExporterInterface {
    protected $whitespace = '    ';
    public function exportArray(array $var): string
    {
        return $this->doExport($var) . ';';
    }

    protected function doExport(array $var, int $deep = 1, ?string $path = null): string
    {
        $content = "[" . PHP_EOL;
        $content .= $this->addWhitespace($deep);

        foreach ($var as $key => $value) {
            $k = $key;
            if (!is_int($key)) {
                $key = "'$key'";
            }

            if (is_array($value)) {
                $path !== null ? $path .= "[$k]" : $path = "[$k]";
                $value = $this->doExport($value, $deep + 1, $path);
                $content .= "$key => $value";
            } elseif ($value instanceof \Closure) {
                $value = export_closure($value);
                $content .= "$key => $value";
            } elseif (is_object($value)) {
                $path !== null ? $path .= "[$k]" : $path = "[$k]";
                throw new ArrayExportException(
                    "The value of the array with the key {$path} is an object and cannot be exported",
                    $value
                );
            } elseif (is_int($value) || is_float($value)) {
                $content .= "$key => $value";
            } elseif (is_bool($value)) {
                $content .= "$key => ";
                $content .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $content .= "$key => null";
            } elseif (is_resource($value)) {
                $path !== null ? $path .= "[$k]" : $path = $k;
                throw new ArrayExportException(
                    "The value of the array with the key {$path} is an resource and cannot be exported"
                );
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
