# Install
```bash
composer require bermudaphp/utils-closure
````

# Usage
```php

$closure = static function (string $filename) use ($fileRider): string {
    return $fileRider->read($filename);
});

dd(closureToString($closure));

^ """
static function(string $filename) use ($fileRider): string {
    return $fileRider->read($filename);
}
"""
