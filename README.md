# Install
```bash
composer require bermudaphp/utils-closure
````

# Usage
```php

$closure = static function (string $filename) use ($fileReader): string {
    return $fileReader->read($filename);
});

dd(closureToString($closure));

^ """
static function(string $filename) use ($fileReader): string {
    return $fileReader->read($filename);
}
"""
