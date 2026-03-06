# Типобезопасная фильтрация REST 3.0

## Обзор

Bitrix24 PHP SDK предоставляет систему типобезопасных построителей фильтров для REST 3.0 API.
Система обеспечивает проверку типов на этапе компиляции, поддержку автодополнения в IDE
и автоматические преобразования типов при полной обратной совместимости.

## Оглавление

1. [Основы фильтрации REST 3.0](#1-основы-фильтрации-rest-30)
2. [Типобезопасность](#2-типобезопасность)
3. [Примеры использования](#3-примеры-использования)
4. [Таблица типов полей](#4-таблица-типов-полей)
5. [Миграция с массивов](#5-миграция-с-массивов)

---

## 1. Основы фильтрации REST 3.0

### Принципы фильтрации

В REST 3.0 фильтрация данных строится на логических выражениях, которые можно комбинировать:

- **Логика AND**: условия на одном уровне объединяются через AND — должны выполняться все одновременно
- **Логика OR**: группы условий можно объединять через OR, передавая специальный объект `{"logic": "or"}`

### Простой фильтр

Найти все записи, где Status = "NEW" И ID равен 3, 4 или 5:

```json
{
    "filter": [
        ["status", "=", "NEW"],
        ["id", "in", [3, 4, 5]]
    ]
}
```

Все элементы массива `filter` объединяются логикой AND.

### Сложный фильтр с логикой OR

Найти все записи, где Status = "NEW" И (ID равен 1 или 2, ИЛИ ID равен 3, 4 или 5):

```json
{
    "filter": [
        ["status", "=", "NEW"],
        {
            "logic": "or",
            "conditions": [
                ["id", "in", [1, 2]],
                ["id", "in", [3, 4, 5]]
            ]
        }
    ]
}
```

**Пояснение:**
1. `["status", "=", "NEW"]` — простое условие: поле status равно NEW
2. `{"logic": "or", "conditions": [...]}` — группа условий, объединённых через OR:
   - `["id", "in", [1, 2]]` — ID должен быть 1 или 2
   - `["id", "in", [3, 4, 5]]` — ID должен быть 3, 4 или 5
3. Итог: Status = NEW И (ID in [1,2] ИЛИ ID in [3,4,5])

### Поддерживаемые операторы

| Оператор  | Описание                        | Пример                                                                  |
|-----------|---------------------------------|-------------------------------------------------------------------------|
| `=`       | равно                           | `["status", "=", "NEW"]` → status точно равен **NEW**                  |
| `!=`      | не равно                        | `["status", "!=", "CLOSED"]` → status не равен **CLOSED**              |
| `>`       | больше                          | `["date", ">", "2025-01-01"]` → после 1 января 2025                    |
| `>=`      | больше или равно                | `["price", ">=", 1000]` → цена от **1000** и выше                      |
| `<`       | меньше                          | `["date", "<", "2025-01-01"]` → до 1 января 2025                       |
| `<=`      | меньше или равно                | `["price", "<=", 1000]` → цена до **1000** включительно                |
| `in`      | одно из значений списка         | `["id", "in", [1, 2, 3]]` → id равен **1**, **2** или **3**            |
| `between` | в диапазоне (включительно)      | `["date", "between", ["2025-01-01", "2025-12-31"]]` → весь 2025 год    |

---

## 2. Типобезопасность

### Проблема

Обычные построители фильтров принимают тип `mixed`, что ведёт к ряду проблем:

```php
// Всё это компилируется, но семантически неверно:
$filter->id()->eq('не-число');           // ID должен быть int!
$filter->changedDate()->eq(12345);       // Дата должна быть DateTime или строкой!
$filter->priority()->between('low', 'high'); // Приоритет должен быть числовым диапазоном!
```

**Проблемы:**
- ❌ Нет проверки типов на этапе компиляции
- ❌ Неявные преобразования типов происходят незаметно
- ❌ Некорректное использование не вызывает предупреждений
- ❌ Плохая поддержка автодополнения в IDE

### Решение: типизированные построители условий

SDK предоставляет специализированные классы построителей для каждого типа поля,
обеспечивая проверку типов на этапе компиляции:

#### IntFieldConditionBuilder

Для целочисленных полей (ID, счётчики, коды статусов):

```php
public function eq(int $value): AbstractFilterBuilder;
public function neq(int $value): AbstractFilterBuilder;
public function gt(int $value): AbstractFilterBuilder;
public function gte(int $value): AbstractFilterBuilder;
public function lt(int $value): AbstractFilterBuilder;
public function lte(int $value): AbstractFilterBuilder;
public function in(array $values): AbstractFilterBuilder;
public function between(int $min, int $max): AbstractFilterBuilder;
```

#### StringFieldConditionBuilder

Для текстовых полей (заголовки, описания):

```php
public function eq(string $value): AbstractFilterBuilder;
public function neq(string $value): AbstractFilterBuilder;
public function in(array $values): AbstractFilterBuilder;
```

#### DateFieldConditionBuilder

Для полей дата/дата-время — принимает как объекты DateTime, так и строки:

```php
public function eq(DateTime|string $value): AbstractFilterBuilder;
public function neq(DateTime|string $value): AbstractFilterBuilder;
public function gt(DateTime|string $value): AbstractFilterBuilder;
public function gte(DateTime|string $value): AbstractFilterBuilder;
public function lt(DateTime|string $value): AbstractFilterBuilder;
public function lte(DateTime|string $value): AbstractFilterBuilder;
public function between(DateTime|string $from, DateTime|string $to): AbstractFilterBuilder;
```

**Автоматическое преобразование:** объекты DateTime конвертируются в формат `Y-m-d`:

```php
->deadline()->eq(new DateTime('2025-01-15'))
// Результат: ['deadline', '=', '2025-01-15']
```

#### BoolFieldConditionBuilder

Для булевых полей — автоматически преобразует в формат Y/N Битрикс24:

```php
public function eq(bool $value): AbstractFilterBuilder;
public function neq(bool $value): AbstractFilterBuilder;
```

**Автоматическое преобразование:**

```php
->favorite()->eq(true)   // Результат: ['favorite', '=', 'Y']
->favorite()->eq(false)  // Результат: ['favorite', '=', 'N']
```

### Преимущества

1. **Проверка типов на этапе компиляции**
   ```php
   $filter->id()->eq(100);       // ✅ Компилируется
   $filter->id()->eq('сто');     // ❌ TypeError на этапе разработки
   ```

2. **Автодополнение в IDE**
   ```php
   $filter->id()->eq(|)          // IDE подсказывает: int
   $filter->title()->eq(|)       // IDE подсказывает: string
   $filter->deadline()->eq(|)    // IDE подсказывает: DateTime|string
   ```

3. **Самодокументируемый код**
   ```php
   // Сигнатура говорит сама за себя:
   public function between(int $min, int $max): AbstractFilterBuilder
   ```

4. **Автоматические преобразования типов**
   ```php
   // Преобразование DateTime
   ->changedDate()->eq(new DateTime('2025-01-01')) // ✅ → '2025-01-01'

   // Преобразование bool
   ->favorite()->eq(true) // ✅ → 'Y'
   ```

---

## 3. Примеры использования

### Базовая фильтрация

```php
use Bitrix24\SDK\Services\Task\Service\TaskFilter;

$filter = (new TaskFilter())
    ->id()->eq(100)
    ->title()->eq('Важная задача')
    ->status()->gte(2);

// Результат:
// [
//     ['id', '=', 100],
//     ['title', '=', 'Важная задача'],
//     ['status', '>=', 2]
// ]
```

### Целочисленные поля

```php
$filter = (new TaskFilter())
    ->id()->eq(100)                      // одно значение
    ->priority()->gte(2)                 // сравнение
    ->responsibleId()->in([1, 2, 3])     // несколько значений
    ->status()->between(1, 5);           // диапазон

// Ошибка на этапе компиляции — неверный тип:
// $filter->id()->eq('не-число'); // ❌ TypeError
```

### Строковые поля

```php
$filter = (new TaskFilter())
    ->title()->eq('Важная задача')
    ->description()->neq('Черновик')
    ->guid()->in(['guid-1', 'guid-2']);

// Ошибка на этапе компиляции — неверный тип:
// $filter->title()->eq(123); // ❌ TypeError
```

### Поля дат

```php
use DateTime;

$filter = (new TaskFilter())
    // Объекты DateTime (автоматически конвертируются в Y-m-d)
    ->changedDate()->eq(new DateTime('2025-01-01'))
    ->deadline()->gt(new DateTime('2025-06-01'))
    ->createdDate()->between(
        new DateTime('2025-01-01'),
        new DateTime('2025-12-31')
    )

    // Строки в формате Y-m-d
    ->closedDate()->lt('2025-12-31')
    ->dateStart()->gte('2025-03-01');
```

### Булевы поля

```php
$filter = (new TaskFilter())
    ->multitask()->eq(true)    // преобразуется в 'Y'
    ->favorite()->eq(false)    // преобразуется в 'N'
    ->isMuted()->neq(true);    // не равно 'Y'

// Ошибка на этапе компиляции — неверный тип:
// $filter->favorite()->eq('да'); // ❌ TypeError
```

### Логика OR

```php
$filter = (new TaskFilter())
    ->status()->eq(2)
    ->or(function (TaskFilter $f) {
        $f->id()->in([1, 2]);
        $f->priority()->gt(3);
    });

// Результат:
// [
//     ['status', '=', 2],
//     {
//         'logic': 'or',
//         'conditions': [
//             ['id', 'in', [1, 2]],
//             ['priority', '>', 3]
//         ]
//     }
// ]
```

### Пользовательские поля (UF_)

```php
// Префикс UF_ добавляется автоматически, если отсутствует
$filter = (new TaskFilter())
    ->title()->eq('Задача')
    ->userField('UF_CRM_TASK')->eq('value')
    ->userField('CRM_PROJECT')->in([1, 2, 3]); // UF_ добавится автоматически

// Результат:
// [
//     ['title', '=', 'Задача'],
//     ['UF_CRM_TASK', '=', 'value'],
//     ['UF_CRM_PROJECT', 'in', [1, 2, 3]]
// ]
```

### Смешанные типы в одном фильтре

```php
use DateTime;

$filter = (new TaskFilter())
    ->id()->eq(100)                                   // int
    ->title()->eq('Срочно')                           // string
    ->changedDate()->eq(new DateTime('2025-01-01'))   // DateTime → '2025-01-01'
    ->favorite()->eq(true)                            // bool → 'Y'
    ->priority()->between(1, 5);                      // диапазон int

// Результат:
// [
//     ['id', '=', 100],
//     ['title', '=', 'Срочно'],
//     ['changedDate', '=', '2025-01-01'],
//     ['favorite', '=', 'Y'],
//     ['priority', 'between', [1, 5]]
// ]
```

### Резервный вариант: сырой массив

Для нестандартных ситуаций или неподдерживаемых сценариев:

```php
$filter = (new TaskFilter())
    ->title()->eq('Задача')
    ->setRaw([
        ['customField', '=', 'value'],
        ['anotherField', '!=', 'test']
    ]);

// Результат: сырой массив используется напрямую
```

### Использование с сервисом Task

```php
// task()->list() принимает TaskFilter|array
$tasks = $b24->getTaskScope()->task()->batch->list(
    filter: (new TaskFilter())
        ->title()->eq('Важное')
        ->deadline()->gt(new DateTime('2025-01-01'))
        ->favorite()->eq(true)
);

// Обратная совместимость с массивами
$tasks = $b24->getTaskScope()->task()->batch->list(
    filter: [
        ['title', '=', 'Важное'],
        ['deadline', '>', '2025-01-01']
    ]
);
```

---

## 4. Таблица типов полей

### Справочник типов

| Тип поля          | Класс построителя              | PHP-типы           | Формат Битрикс24 | Пример                               |
|-------------------|--------------------------------|--------------------|------------------|--------------------------------------|
| Целое число       | `IntFieldConditionBuilder`     | `int`              | `int`            | `->id()->eq(100)`                    |
| Строка            | `StringFieldConditionBuilder`  | `string`           | `string`         | `->title()->eq('Задача')`            |
| Дата/дата-время   | `DateFieldConditionBuilder`    | `DateTime\|string` | `string` (Y-m-d) | `->deadline()->eq(new DateTime())`   |
| Булево            | `BoolFieldConditionBuilder`    | `bool`             | `string` (Y/N)   | `->favorite()->eq(true)`             |
| Польз. поля (UF_) | `FieldConditionBuilder`        | `mixed`            | `mixed`          | `->userField('UF_CODE')->eq($value)` |

### Типы полей TaskFilter

**Целочисленные поля:**
- **Идентификаторы**: `id`, `parentId`, `groupId`, `stageId`, `forumTopicId`, `sprintId`
- **Статусы**: `status`, `priority`, `mark`
- **Люди**: `createdBy`, `responsibleId`, `changedBy`, `closedBy`
- **Числа**: `timeEstimate`, `commentsCount`, `durationPlan`

**Строковые поля:**
- `title`, `description`, `xmlId`, `guid`

**Поля дат:**
- `createdDate`, `changedDate`, `closedDate`, `deadline`, `dateStart`, `startDatePlan`, `endDatePlan`

**Булевы поля:**
- `multitask`, `taskControl`, `subordinate`, `favorite`, `isMuted`

---

## 5. Миграция с массивов

### Сравнение: массив vs типобезопасный фильтр

```php
// Было: массив (работает, но без проверки типов)
$filter = [
    ['id', '=', 100],
    ['title', '=', 'Задача'],
    ['deadline', '>', '2025-01-01'],
    ['favorite', '=', 'Y'],
];

// Стало: типобезопасный построитель
$filter = (new TaskFilter())
    ->id()->eq(100)
    ->title()->eq('Задача')
    ->deadline()->gt(new DateTime('2025-01-01'))
    ->favorite()->eq(true);
```

### Обратная совместимость

- ✅ Все существующие методы фильтрации остаются доступными
- ✅ Интерфейс API не изменился — строже стали только типы
- ✅ Обобщённый `FieldConditionBuilder` по-прежнему доступен для пользовательских полей
- ✅ Резервный `setRaw()` для нестандартных случаев
- ✅ `task()->batch->list()` принимает `TaskFilter|array` через union type

> **Внимание:** код, который передавал значения неверного типа, теперь будет вызывать ошибку на этапе компиляции. Это сделано намеренно — ошибки обнаруживаются раньше в цикле разработки.
