# VarExporter

**[Русская версия](README_ru.md)**

PHP library for exporting variables to their string representation with advanced formatting options and closure support.

## Features

- **Multiple data types support**: arrays, closures, scalar values, and more
- **Two formatting modes**: standard (compact) and pretty (indented)
- **Closure export**: Full closure source code extraction with namespace resolution
- **Configurable formatting**: Custom indentation, key sorting, trailing commas
- **Special value handling**: INF, NAN, magic constants
- **PHP-Parser integration**: Advanced AST analysis for closures
- **Exception handling**: Detailed error information for debugging

## Installation

```bash
composer require bermudaphp/var-export
```

## Quick Start

```php
use Bermuda\VarExport\VarExporter;

// Simple array export
$array = ['foo' => 'bar', 'nested' => [1, 2, 3]];
echo VarExporter::export($array);
// Output: ['foo' => 'bar', 'nested' => [1, 2, 3]]

// Pretty formatting
echo VarExporter::exportPretty($array);
// Output:
// [
//     'foo' => 'bar',
//     'nested' => [
//         1,
//         2,
//         3
//     ]
// ]
```

## Usage Examples

### Basic Variable Export

```php
use Bermuda\VarExport\VarExporter;

// Scalar values
echo VarExporter::export(42);           // 42
echo VarExporter::export('hello');      // 'hello'
echo VarExporter::export(true);         // true
echo VarExporter::export(null);         // null

// Special float values
echo VarExporter::export(INF);          // INF
echo VarExporter::export(-INF);         // -INF
echo VarExporter::export(NAN);          // NAN
```

### Array Export

```php
use Bermuda\VarExport\VarExporter;

$data = [
    'users' => [
        ['name' => 'John', 'age' => 30],
        ['name' => 'Jane', 'age' => 25]
    ],
    'config' => [
        'debug' => true,
        'timeout' => 30
    ]
];

// Standard formatting (single line)
echo VarExporter::export($data);

// Pretty formatting (multi-line with indentation)
echo VarExporter::exportPretty($data);
```

### Closure Export

```php
use Bermuda\VarExport\VarExporter;

$multiplier = 2;
$closure = function($x) use ($multiplier) {
    return $x * $multiplier;
};

// Export closure with full source code
echo VarExporter::export($closure);
// Output: function($x) use ($multiplier) { return $x * $multiplier; }

class A {
    public function call()
    {
        $closure = fn(): string => self::class ;
        dd(VarExporter::export($closure));
    }
}

^ "fn(): string => \A::class"
```

### Configuration Options

```php
use Bermuda\VarExport\{VarExporter, FormatterConfig, FormatterMode};

$config = new FormatterConfig(
    mode: FormatterMode::PRETTY,
    indent: '  ',                    // 2 spaces instead of 4
    maxDepth: 50,                   // Maximum nesting depth
    sortKeys: true,                 // Sort array keys
    trailingComma: true            // Add trailing comma in arrays
);

$array = ['c' => 3, 'a' => 1, 'b' => 2];
echo VarExporter::export($array, $config);
```

### Using Convenience Functions

```php
use function Bermuda\VarExport\{export_var, export_array, export_closure};

// Export any variable with pretty formatting by default
echo export_var(['foo' => 'bar']);

// Export array specifically
echo export_array([1, 2, 3]);

// Export closure specifically
$fn = fn($x) => $x * 2;
echo export_closure($fn);
```

## Advanced Configuration

### Custom Formatting

```php
use Bermuda\VarExport\{VarExporter, FormatterConfig, FormatterMode};

// Create custom configuration
$config = new FormatterConfig(
    mode: FormatterMode::PRETTY,
    indent: "\t",                   // Use tabs
    sortKeys: true,                 // Sort keys alphabetically
    trailingComma: true            // Add trailing commas
);

// Apply to specific export
$result = VarExporter::export($data, $config);

// Set as default for all exports
VarExporter::setDefaultConfig($config);
```

### Custom Exporters

```php
use Bermuda\VarExport\{VarExporter, ArrayExporter, ClosureExporter};

// Register custom array exporter
$customArrayExporter = new ArrayExporter($customConfig);
VarExporter::setExporter($customArrayExporter);

// Register custom closure exporter
$customClosureExporter = new ClosureExporter($customConfig);
VarExporter::setExporter($customClosureExporter);
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `mode` | `FormatterMode` | `STANDARD` | Formatting mode (STANDARD or PRETTY) |
| `indent` | `string` | `'    '` | Indentation string (4 spaces by default) |
| `maxDepth` | `int` | `100` | Maximum nesting depth to prevent infinite recursion |
| `sortKeys` | `bool` | `false` | Whether to sort array keys |
| `trailingComma` | `bool` | `false` | Whether to add trailing commas in arrays |

## Supported Types

### ✅ Supported
- **Arrays**: Indexed and associative arrays with full nesting support
- **Closures**: Anonymous functions and arrow functions with source code extraction
- **Scalars**: integers, floats, strings, booleans, null
- **Special values**: INF, -INF, NAN

### ❌ Not Supported
- **Objects**: Regular objects (except closures)
- **Resources**: File handles, database connections, etc.

## Error Handling

The library provides detailed exception information:

```php
use Bermuda\VarExport\{VarExporter, ExportException};

try {
    $object = new stdClass();
    VarExporter::export($object);
} catch (ExportException $e) {
    echo "Export failed: " . $e->getMessage();
    // Access the problematic variable
    $problematicVar = $e->var;
}
```

## Requirements

- PHP 8.1 or higher
- nikic/php-parser (for closure export functionality)
