<?php

namespace Bermuda\VarExport;

/**
 * Main variable exporter that handles all PHP variable types.
 *
 * This is the primary entry point for exporting PHP variables to their
 * string representation. It delegates to specialized exporters based on
 * the variable type and provides both standard and pretty formatting modes.
 */
final class VarExporter
{
    /** @var array<string, object> Custom exporters by interface */
    private static array $exporters = [];

    /** @var FormatterConfig Default formatter configuration */
    private static FormatterConfig $defaultConfig;

    /**
     * Export a variable to its string representation.
     *
     * @param mixed $var The variable to export
     * @param FormatterConfig|null $config Optional formatter configuration
     * @return string The exported variable as a string
     * @throws ExportException If the variable type cannot be exported
     */
    public static function export(mixed $var, ?FormatterConfig $config = null): string
    {
        $config ??= self::getDefaultConfig();

        return match (true) {
            $var instanceof \Closure => self::getClosureExporter($config)->exportClosure($var),
            is_array($var) => self::getArrayExporter($config)->exportArray($var),
            is_int($var), is_float($var) => self::formatNumeric($var),
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

    /**
     * Export a variable with pretty formatting.
     *
     * @param mixed $var The variable to export
     * @param FormatterConfig|null $config Optional formatter configuration
     * @return string The exported variable as a pretty-formatted string
     */
    public static function exportPretty(mixed $var, ?FormatterConfig $config = null): string
    {
        $config ??= self::getDefaultConfig();
        $prettyConfig = $config->withMode(FormatterMode::PRETTY);
        return self::export($var, $prettyConfig);
    }

    /**
     * Set the default formatter configuration.
     *
     * @param FormatterConfig $config The new default configuration
     */
    public static function setDefaultConfig(FormatterConfig $config): void
    {
        self::$defaultConfig = $config;
    }

    /**
     * Get the current default formatter configuration.
     *
     * @return FormatterConfig The default configuration
     */
    public static function getDefaultConfig(): FormatterConfig
    {
        return self::$defaultConfig ??= new FormatterConfig();
    }

    /**
     * Register a custom exporter for specific types.
     *
     * @param ArrayExporterInterface|ClosureExporterInterface $exporter The custom exporter
     */
    public static function setExporter(ArrayExporterInterface|ClosureExporterInterface $exporter): void
    {
        $interfaces = [ArrayExporterInterface::class, ClosureExporterInterface::class];
        foreach ($interfaces as $interface) {
            if ($exporter instanceof $interface) {
                self::$exporters[$interface] = $exporter;
            }
        }
    }

    /**
     * Format numeric values handling special float cases.
     *
     * @param int|float $value The numeric value to format
     * @return string The formatted numeric string
     */
    private static function formatNumeric(int|float $value): string
    {
        if (is_float($value)) {
            if (is_infinite($value)) {
                return $value > 0 ? 'INF' : '-INF';
            }
            if (is_nan($value)) {
                return 'NAN';
            }
        }

        return (string)$value;
    }

    /**
     * Get or create an array exporter with specified configuration.
     *
     * @param FormatterConfig $config The formatter configuration
     * @return ArrayExporter The array exporter instance
     */
    private static function getArrayExporter(FormatterConfig $config): ArrayExporter
    {
        $key = ArrayExporterInterface::class;
        if (!isset(self::$exporters[$key]) ||
            !self::$exporters[$key] instanceof ArrayExporter) {
            return new ArrayExporter($config);
        }

        return self::$exporters[$key]->withConfig($config);
    }

    /**
     * Get or create a closure exporter with specified configuration.
     *
     * @param FormatterConfig $config The formatter configuration
     * @return ClosureExporter The closure exporter instance
     */
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
