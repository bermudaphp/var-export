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

$closure = static fn(): string => __FILE__ ;

dd(export_closure($closure));

^ "static fn(): string => 'path/to/closure/filename'"

class A {
    public function call()
    {
        $closure = fn(): string => self::class ;
        dd(export_closure($closure));
    }
}

^ "fn(): string => \A::class"

```

