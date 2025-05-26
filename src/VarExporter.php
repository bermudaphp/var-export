<?php

namespace Bermuda\VarExport;

use Bermuda\VarExport\Formatter\FormatterMode;
use Bermuda\VarExport\Formatter\FormatterConfig;

final class VarExporter
{
    private static array $exporters = [];
    private static FormatterConfig $defaultConfig;

    public static function export(mixed $var, ?FormatterConfig $config = null): string
    {
        $config ??= self::getDefaultConfig();

        return match (true) {
            $var instanceof \Closure => self::getClosureExporter($config)->exportClosure($var),
            is_array($var) => self::getArrayExporter($config)->exportArray($var),
            is_int($var), is_float($var) => (string)$var,
            is_string($var) => "'" . addcslashes($var, "'\\") . "'",
            is_bool($var) => $var ? 'true' : 'false',
            is_null($var) => 'null',
            is_object($var) => throw new ExportException(
                'Object of type ' . get_class($var) . ' cannot be exported',
                $var
            ),
            is_resource($var) => throw new ExportException(
                'Resource of type ' . get_resource_type($var) . ' cannot be exported'
            ),
            default => throw new ExportException(
                'Unsupported variable type: ' . gettype($var),
                $var
            )
        };
    }

    public static function exportPretty(mixed $var, ?FormatterConfig $config = null): string
    {
        $config ??= self::getDefaultConfig();
        $prettyConfig = $config->withMode(FormatterMode::PRETTY);
        return self::export($var, $prettyConfig);
    }

    public static function setDefaultConfig(FormatterConfig $config): void
    {
        self::$defaultConfig = $config;
    }

    public static function getDefaultConfig(): FormatterConfig
    {
        return self::$defaultConfig ??= new FormatterConfig();
    }

    public static function setExporter(ArrayExporterInterface|ClosureExporterInterface $exporter): void
    {
        $interfaces = [ArrayExporterInterface::class, ClosureExporterInterface::class];
        foreach ($interfaces as $interface) {
            if ($exporter instanceof $interface) {
                self::$exporters[$interface] = $exporter;
            }
        }
    }

    private static function getArrayExporter(FormatterConfig $config): ArrayExporter
    {
        $key = ArrayExporterInterface::class;
        if (!isset(self::$exporters[$key]) ||
            !self::$exporters[$key] instanceof ArrayExporter) {
            return new ArrayExporter($config);
        }

        return self::$exporters[$key]->withConfig($config);
    }

    private static function getClosureExporter(FormatterConfig $config): ClosureExporter
    {
        $key = ClosureExporterInterface::class;
        if (!isset(self::$exporters[$key]) ||
            !self::$exporters[$key] instanceof ClosureExporter) {
            return new ClosureExporter($config);
        }

        return self::$exporters[$key]->withConfig($config);
    }
}
