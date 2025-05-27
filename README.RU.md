# VarExporter

**[English version](README.md)**

Мощная PHP библиотека для экспорта переменных в их строковое представление с расширенными возможностями форматирования и поддержкой замыканий.

## Возможности

- **Поддержка множества типов данных**: массивы, замыкания, скалярные значения и многое другое
- **Два режима форматирования**: стандартный (компактный) и красивый (с отступами)
- **Экспорт замыканий**: полное извлечение исходного кода замыканий с разрешением пространств имён
- **Настраиваемое форматирование**: пользовательские отступы, сортировка ключей, завершающие запятые
- **Обработка особых значений**: INF, NAN, магические константы
- **Интеграция с PHP-Parser**: продвинутый анализ AST для замыканий
- **Обработка исключений**: подробная информация об ошибках для отладки

## Установка

```bash
composer require bermudaphp/var-export
```

## Быстрый старт

```php
use Bermuda\VarExport\VarExporter;

// Простой экспорт массива
$array = ['foo' => 'bar', 'nested' => [1, 2, 3]];
echo VarExporter::export($array);
// Вывод: ['foo' => 'bar', 'nested' => [1, 2, 3]]

// Красивое форматирование
echo VarExporter::exportPretty($array);
// Вывод:
// [
//     'foo' => 'bar',
//     'nested' => [
//         1,
//         2,
//         3
//     ]
// ]
```

## Примеры использования

### Базовый экспорт переменных

```php
use Bermuda\VarExport\VarExporter;

// Скалярные значения
echo VarExporter::export(42);           // 42
echo VarExporter::export('привет');     // 'привет'
echo VarExporter::export(true);         // true
echo VarExporter::export(null);         // null

// Особые значения с плавающей точкой
echo VarExporter::export(INF);          // INF
echo VarExporter::export(-INF);         // -INF
echo VarExporter::export(NAN);          // NAN
```

### Экспорт массивов

```php
use Bermuda\VarExport\VarExporter;

$data = [
    'users' => [
        ['name' => 'Иван', 'age' => 30],
        ['name' => 'Мария', 'age' => 25]
    ],
    'config' => [
        'debug' => true,
        'timeout' => 30
    ]
];

// Стандартное форматирование (одна строка)
echo VarExporter::export($data);

// Красивое форматирование (многострочное с отступами)
echo VarExporter::exportPretty($data);
```

### Экспорт замыканий

```php
use Bermuda\VarExport\VarExporter;

$multiplier = 2;
$closure = function($x) use ($multiplier) {
    return $x * $multiplier;
};

// Экспорт замыкания с полным исходным кодом
echo VarExporter::export($closure);
// Вывод: function($x) use ($multiplier) { return $x * $multiplier; }

class A {
    public function call()
    {
        $closure = fn(): string => self::class ;
        dd(VarExporter::export($closure));
    }
}

^ "fn(): string => \A::class"
```

### Параметры конфигурации

```php
use Bermuda\VarExport\{VarExporter, FormatterConfig, FormatterMode};

$config = new FormatterConfig(
    mode: FormatterMode::PRETTY,
    indent: '  ',                    // 2 пробела вместо 4
    maxDepth: 50,                   // Максимальная глубина вложенности
    sortKeys: true,                 // Сортировать ключи массива
    trailingComma: true            // Добавлять завершающую запятую в массивах
);

$array = ['c' => 3, 'a' => 1, 'b' => 2];
echo VarExporter::export($array, $config);
```

### Использование вспомогательных функций

```php
use function Bermuda\VarExport\{export_var, export_array, export_closure};

// Экспорт любой переменной с красивым форматированием по умолчанию
echo export_var(['foo' => 'bar']);

// Экспорт массива специально
echo export_array([1, 2, 3]);

// Экспорт замыкания специально
$fn = fn($x) => $x * 2;
echo export_closure($fn);
```

## Расширенная конфигурация

### Пользовательское форматирование

```php
use Bermuda\VarExport\{VarExporter, FormatterConfig, FormatterMode};

// Создание пользовательской конфигурации
$config = new FormatterConfig(
    mode: FormatterMode::PRETTY,
    indent: "\t",                   // Использовать табуляцию
    sortKeys: true,                 // Сортировать ключи по алфавиту
    trailingComma: true            // Добавлять завершающие запятые
);

// Применить к конкретному экспорту
$result = VarExporter::export($data, $config);

// Установить как по умолчанию для всех экспортов
VarExporter::setDefaultConfig($config);
```

### Пользовательские экспортеры

```php
use Bermuda\VarExport\{VarExporter, ArrayExporter, ClosureExporter};

// Зарегистрировать пользовательский экспортер массивов
$customArrayExporter = new ArrayExporter($customConfig);
VarExporter::setExporter($customArrayExporter);

// Зарегистрировать пользовательский экспортер замыканий
$customClosureExporter = new ClosureExporter($customConfig);
VarExporter::setExporter($customClosureExporter);
```

## Параметры конфигурации

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `mode` | `FormatterMode` | `STANDARD` | Режим форматирования (STANDARD или PRETTY) |
| `indent` | `string` | `'    '` | Строка отступа (4 пробела по умолчанию) |
| `maxDepth` | `int` | `100` | Максимальная глубина вложенности для предотвращения бесконечной рекурсии |
| `sortKeys` | `bool` | `false` | Сортировать ли ключи массива |
| `trailingComma` | `bool` | `false` | Добавлять ли завершающие запятые в массивах |

## Поддерживаемые типы

### ✅ Поддерживаются
- **Массивы**: индексированные и ассоциативные массивы с полной поддержкой вложенности
- **Замыкания**: анонимные функции и стрелочные функции с извлечением исходного кода
- **Скаляры**: целые числа, числа с плавающей точкой, строки, логические значения, null
- **Особые значения**: INF, -INF, NAN

### ❌ Не поддерживаются
- **Объекты**: обычные объекты (кроме замыканий)
- **Ресурсы**: файловые дескрипторы, соединения с базой данных и т.д.

## Обработка ошибок

Библиотека предоставляет подробную информацию об исключениях:

```php
use Bermuda\VarExport\{VarExporter, ExportException};

try {
    $object = new stdClass();
    VarExporter::export($object);
} catch (ExportException $e) {
    echo "Экспорт не удался: " . $e->getMessage();
    // Доступ к проблемной переменной
    $problematicVar = $e->var;
}
```

## Требования

- PHP 8.1 или выше
- nikic/php-parser (для функциональности экспорта замыканий)
