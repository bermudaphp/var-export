<?php

namespace Bermuda\VarExport;

final class VarExporter
{
    private static $exporters = [];
    public static function export(mixed $var): string
    {
        if ($var instanceof \Closure) return self::getExporter(ClosureExporterInterface::class)->exportClosure($var);
        if (is_array($var)) return self::getExporter(ArrayExporterInterface::class);
        if (is_int($var) || is_float($var) || is_string($var)) return $var;
        if (is_bool($var)) return $var ? 'true' : 'false';
        if (is_object($var)) throw new ExportException('The variable is an object and cannot be exported', $var);
        if (is_resource($var)) throw new ExportException('the variable is an resource and cannot be exported', $var);
    }

    /**
     * @param ArrayExporterInterface|ClosureExporterInterface $exporter
     * @return void
     */
    public static function setExporter(ArrayExporterInterface|ClosureExporterInterface $exporter): void
    {
        foreach ([ClosureExporterInterface::class, ArrayExporterInterface::class] as $i) {
            if ($exporter instanceof $i) self::$exporters[$i] = $exporter;
        }
    }

    private function getExporter(string $class): array
    {
        if (!isset(self::$exporters[$class])) {
            if ($class == ArrayExporterInterface::class) {
                return self::$exporters[$class] = new ArrayExporter;
            } else {
                return self::$exporters[$class] = new ClosureExporter;
            }
        }
    }
}