# Install
```bash
composer require bermudaphp/var-export
````

# Usage
```php

$closure = static function (string $filename) use ($fileReader): string {
    return $fileReader->read($filename);
});

dd(export_closure($closure));

^ """
static function(string $filename) use($fileReader): string {
    return $fileReader->read($filename);
}
"""


