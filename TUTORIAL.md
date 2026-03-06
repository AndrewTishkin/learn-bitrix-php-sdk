# Обучающий материал: Bitrix24 PHP SDK

## Содержание

1. [Общая архитектура SDK](#1-общая-архитектура-sdk)
2. [Как связаны SDK и документация API](#2-как-связаны-sdk-и-документация-api)
3. [Установка и авторизация через веб-хук](#3-установка-и-авторизация-через-веб-хук)
4. [Пример 1: Получить задачи с фильтрацией](#4-пример-1-получить-задачи-с-фильтрацией)
5. [Пример 2: Получить последние 200 изменённых контактов](#5-пример-2-получить-последние-200-изменённых-контактов)
6. [Понимание результатов (DTO)](#6-понимание-результатов-dto)
7. [Batch-запросы: обход пагинации автоматически](#7-batch-запросы-обход-пагинации-автоматически)
8. [Шпаргалка: от документации к коду](#8-шпаргалка-от-документации-к-коду)

---

## 1. Общая архитектура SDK

SDK построен на трёх слоях:

```
┌─────────────────────────────────────────────────────────┐
│  Ваш код                                                │
│  $b24->getCRMScope()->contact()->batch->list(...)       │
└───────────────────────────┬─────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────┐
│  Services (Сервисы)                                     │
│  ServiceBuilder → CRMServiceBuilder → Contact/Task      │
│  Работают с именованными сущностями Битрикс             │
│  Возвращают типизированные DTO-объекты                  │
└───────────────────────────┬─────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────┐
│  Core (Ядро)                                            │
│  ApiClient → HTTP-запрос → JSON → ResponseInterface     │
│  Авторизация, повтор токена, пагинация                  │
└─────────────────────────────────────────────────────────┘
```

**Ключевые классы:**

| Класс | Что делает |
|-------|-----------|
| `ServiceBuilderFactory` | Фабрика — создаёт главный сервис-билдер из веб-хука или OAuth-токена |
| `ServiceBuilder` | Точка входа в SDK. Отдаёт scope-билдеры (`getCRMScope()`, `getTaskScope()`, ...) |
| `CRMServiceBuilder` | Билдер для CRM. Отдаёт конкретные сервисы (`contact()`, `deal()`, ...) |
| `TaskServiceBuilder` | Билдер для задач. Отдаёт `task()`, `commentitem()`, ... |
| `Contact` / `Task` | Конкретные сервисы с методами `list()`, `add()`, `update()`, `delete()` |
| `Contact::$batch` / `Task::$batch` | Batch-версии тех же операций — для работы с большими объёмами данных |

---

## 2. Как связаны SDK и документация API

Битрикс24 предоставляет REST API. Каждый метод API имеет имя вида `crm.contact.list` или `tasks.task.list`.

**Правило:** каждый PHP-метод в SDK — это обёртка над конкретным REST-методом API.

Посмотрим на связь на примере:

```
Документация: api-reference/crm/contacts/crm-contact-list.md
               ↓
REST метод:    crm.contact.list
               ↓
PHP класс:     Bitrix24\SDK\Services\CRM\Contact\Service\Contact::list()
               ↓
Вызов ядра:    $this->core->call('crm.contact.list', [...])
```

В каждом методе SDK есть атрибут `#[ApiEndpointMetadata]`, который прямо указывает:
- имя REST-метода
- ссылку на документацию

```php
#[ApiEndpointMetadata(
    'crm.contact.list',                  // ← имя REST-метода
    'https://training.bitrix24.com/...',  // ← ссылка на документацию
    'Returns a list of contacts'
)]
public function list(array $order, array $filter, array $select, int $start): ContactsResult
```

**Вывод:** когда вы видите в документации параметр `filter`, `order`, `select` — они напрямую передаются в соответствующий PHP-метод SDK.

---

## 3. Установка и авторизация через веб-хук

### Установка через Composer

```bash
# Стабильная версия v1 (PHP 8.2+)
composer require bitrix24/b24phpsdk:"^1.0"

# Версия v3 (PHP 8.4+, активная разработка)
composer require bitrix24/b24phpsdk:"^3.0"
```

### Получение веб-хука в Битрикс24

1. Откройте портал Битрикс24
2. Слева: **Разработчикам** → **Другое** → **Входящий вебхук**
3. Назначьте разрешения (scope): `task`, `crm`
4. Скопируйте URL вида: `https://your-portal.bitrix24.com/rest/1/abc123xyz/`

### Инициализация SDK (v1)

```php
<?php
declare(strict_types=1);

use Bitrix24\SDK\Services\ServiceBuilderFactory;

require_once 'vendor/autoload.php';

$webhookUrl = 'https://your-portal.bitrix24.com/rest/1/abc123xyz/';

// Одна строка — и SDK готов к работе
$b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook($webhookUrl);
```

После этого `$b24` — это объект `ServiceBuilder`, через который вы получаете доступ ко всем сервисам.

### Дерево доступа к сервисам

```php
$b24->getTaskScope()->task()           // задачи
$b24->getTaskScope()->commentitem()    // комментарии к задачам
$b24->getCRMScope()->contact()         // CRM-контакты
$b24->getCRMScope()->deal()            // CRM-сделки
$b24->getCRMScope()->lead()            // CRM-лиды
$b24->getMainScope()->main()           // методы main.*
$b24->getUserScope()->user()           // пользователи
```

---

## 4. Пример 1: Получить задачи с фильтрацией

**Задача:** получить все задачи, созданные после 1 января 2025 года, где исполнитель — пользователь с ID 42.

### Как это выглядит в документации API

Файл: `b24-rest-docs-main/api-reference/tasks/tasks-task-list.md`

Параметры фильтра:
- `CREATED_DATE` — дата создания. Поддерживает префиксы: `>=`, `>`, `<=`, `<`
- `RESPONSIBLE_ID` — исполнитель (ID пользователя)

### Вариант А: Одна страница (до 50 записей)

```php
<?php
declare(strict_types=1);

use Bitrix24\SDK\Services\ServiceBuilderFactory;

require_once 'vendor/autoload.php';

$b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook(
    'https://your-portal.bitrix24.com/rest/1/abc123xyz/'
);

// Получить одну страницу задач (до 50 штук)
$result = $b24->getTaskScope()->task()->list(
    // order: сортировка — по дате создания, от новых к старым
    order: ['CREATED_DATE' => 'desc'],

    // filter: фильтр
    filter: [
        '>=CREATED_DATE' => '2025-01-01',  // дата создания >= 2025-01-01
        'RESPONSIBLE_ID' => 42,             // исполнитель с ID 42
    ],

    // select: какие поля вернуть
    select: ['ID', 'TITLE', 'CREATED_DATE', 'RESPONSIBLE_ID', 'STATUS', 'DEADLINE'],

    // start: смещение для пагинации (0 = первая страница)
    start: 0
);

// $result — объект TasksResult. Обращаемся к задачам:
foreach ($result->getTasks() as $task) {
    // Поля доступны как свойства объекта TaskItemResult (camelCase)
    echo "ID: {$task->id}\n";
    echo "Название: {$task->title}\n";
    echo "Создана: {$task->createdDate?->format('Y-m-d')}\n";
    echo "Исполнитель: {$task->responsibleId}\n";
    echo "---\n";
}

// Проверить, есть ли ещё страницы
$total = $result->getCoreResponse()->getResponseData()->getPagination()->getTotal();
echo "Всего задач: {$total}\n";
```

### Вариант Б: Все задачи через batch (любое количество)

Когда задач больше 50, используйте `batch->list()`. Этот метод автоматически делает все нужные запросы и возвращает PHP-генератор.

```php
<?php
declare(strict_types=1);

use Bitrix24\SDK\Services\ServiceBuilderFactory;

require_once 'vendor/autoload.php';

$b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook(
    'https://your-portal.bitrix24.com/rest/1/abc123xyz/'
);

// batch->list() — возвращает Generator, обходит пагинацию автоматически
$tasksGenerator = $b24->getTaskScope()->task()->batch->list(
    order: ['CREATED_DATE' => 'desc'],
    filter: [
        '>=CREATED_DATE' => '2025-01-01',
        'RESPONSIBLE_ID' => 42,
    ],
    select: ['ID', 'TITLE', 'CREATED_DATE', 'RESPONSIBLE_ID', 'STATUS'],
    limit: null  // null = без ограничений, получить все
);

$count = 0;
foreach ($tasksGenerator as $task) {
    // $task — объект TaskItemResult
    echo "#{$task->id}: {$task->title}\n";
    $count++;
}
echo "Получено задач: {$count}\n";
```

### Синтаксис фильтров (важно!)

Префиксы перед именем поля задают оператор сравнения:

```php
$filter = [
    '>=CREATED_DATE' => '2025-01-01',   // дата >= 2025-01-01
    '<=CREATED_DATE' => '2025-12-31',   // дата <= 2025-12-31
    '!STATUS'        => 5,              // статус != 5 (не завершена)
    'RESPONSIBLE_ID' => 42,             // точное совпадение (по умолчанию =)
];
```

Поддерживаемые префиксы для задач: `!`, `<`, `<=`, `>`, `>=`

### Справочник полей задачи (CREATED_DATE vs createdDate)

В документации API поля называются `UPPER_SNAKE_CASE` (например, `CREATED_DATE`).
В PHP-объекте результата они доступны как `camelCase` (например, `createdDate`).

| API-поле (фильтр/select) | PHP-свойство объекта | Тип |
|--------------------------|---------------------|-----|
| `ID` | `$task->id` | `int` |
| `TITLE` | `$task->title` | `string` |
| `CREATED_DATE` | `$task->createdDate` | `CarbonImmutable\|null` |
| `DEADLINE` | `$task->deadline` | `CarbonImmutable\|null` |
| `RESPONSIBLE_ID` | `$task->responsibleId` | `int\|null` |
| `STATUS` | `$task->status` | `int\|null` |
| `GROUP_ID` | `$task->groupId` | `int\|null` |
| `CREATED_BY` | `$task->createdBy` | `int\|null` |
| `DESCRIPTION` | `$task->description` | `string\|null` |

---

## 5. Пример 2: Получить последние 200 изменённых контактов

**Задача:** получить 200 контактов, отсортированных по дате изменения (от новых к старым), изменённых после 1 февраля 2025 года.

### Как это выглядит в документации API

Файл: `b24-rest-docs-main/api-reference/crm/contacts/crm-contact-list.md`

Нужные параметры:
- `order`: `DATE_MODIFY => DESC` — сортировка по дате изменения
- `filter`: `>=DATE_MODIFY` — фильтр по дате изменения
- `select`: список нужных полей

### Вариант А: Через batch с лимитом 200

```php
<?php
declare(strict_types=1);

use Bitrix24\SDK\Services\ServiceBuilderFactory;

require_once 'vendor/autoload.php';

$b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook(
    'https://your-portal.bitrix24.com/rest/1/abc123xyz/'
);

// batch->list() с limit=200 вернёт не более 200 контактов
$contactsGenerator = $b24->getCRMScope()->contact()->batch->list(
    order: ['DATE_MODIFY' => 'DESC'],
    filter: [
        '>=DATE_MODIFY' => '2025-02-01',
    ],
    select: ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL', 'DATE_MODIFY', 'ASSIGNED_BY_ID'],
    limit: 200  // ограничение на количество записей
);

$contacts = [];
foreach ($contactsGenerator as $contact) {
    // $contact — объект ContactItemResult
    $contacts[] = $contact;

    echo "ID: {$contact->ID}\n";
    echo "Имя: {$contact->NAME} {$contact->LAST_NAME}\n";
    echo "Изменён: {$contact->DATE_MODIFY?->format('Y-m-d H:i')}\n";
    echo "---\n";
}

echo "Получено контактов: " . count($contacts) . "\n";
```

> **Примечание:** В CRM-контактах (v1 SDK) поля в результате доступны напрямую через имена как в API (`$contact->NAME`, `$contact->DATE_MODIFY`), в отличие от задач, где используется camelCase.

### Вариант Б: Через обычный list с ручной пагинацией

Если вам нужна точно одна страница (50 записей):

```php
// Первая страница (50 записей)
$result = $b24->getCRMScope()->contact()->list(
    order: ['DATE_MODIFY' => 'DESC'],
    filter: ['>=DATE_MODIFY' => '2025-02-01'],
    select: ['ID', 'NAME', 'LAST_NAME', 'DATE_MODIFY'],
    start: 0
);

foreach ($result->getContacts() as $contact) {
    echo "{$contact->ID}: {$contact->NAME} {$contact->LAST_NAME}\n";
}

// Вторая страница (записи 51–100)
$result2 = $b24->getCRMScope()->contact()->list(
    order: ['DATE_MODIFY' => 'DESC'],
    filter: ['>=DATE_MODIFY' => '2025-02-01'],
    select: ['ID', 'NAME', 'LAST_NAME', 'DATE_MODIFY'],
    start: 50
);
```

### Справочник полей контакта

| API-поле (фильтр/select) | Описание |
|--------------------------|----------|
| `ID` | ID контакта |
| `NAME` | Имя |
| `LAST_NAME` | Фамилия |
| `SECOND_NAME` | Отчество |
| `PHONE` | Телефоны (массив) |
| `EMAIL` | Email-адреса (массив) |
| `DATE_CREATE` | Дата создания |
| `DATE_MODIFY` | Дата изменения |
| `ASSIGNED_BY_ID` | Ответственный (ID пользователя) |
| `COMPANY_ID` | Привязанная компания |
| `TYPE_ID` | Тип контакта |
| `SOURCE_ID` | Источник |

---

## 6. Понимание результатов (DTO)

SDK возвращает не массивы, а типизированные объекты. Это удобно: IDE подсказывает поля.

```
REST API Response (JSON)
        │
        ▼
CoreResponse (обёртка над HTTP-ответом)
        │
        ▼
TasksResult / ContactsResult  ← конкретный Result-класс
        │
        ▼
TaskItemResult / ContactItemResult  ← один элемент
```

**Как получить данные:**

```php
// Для обычных (не batch) запросов:
$result = $b24->getCRMScope()->contact()->list(...);

$contacts = $result->getContacts();  // массив ContactItemResult
$pagination = $result->getCoreResponse()->getResponseData()->getPagination();
$total = $pagination->getTotal();    // всего записей
$next  = $pagination->getNext();     // следующая страница (null если последняя)

// Для batch (генератор):
foreach ($b24->getCRMScope()->contact()->batch->list(...) as $contact) {
    // $contact уже ContactItemResult, пагинация скрыта внутри SDK
}
```

---

## 7. Batch-запросы: обход пагинации автоматически

Битрикс24 отдаёт максимум 50 записей за один запрос. Если нужно больше — придётся делать несколько запросов.

SDK решает это через `batch->list()`:
- Внутри используются PHP-генераторы (`yield`)
- SDK сам делает все дополнительные запросы по мере итерации
- Память расходуется минимально (обрабатываем по одному элементу)

```php
// ❌ Плохо: только 50 записей
$result = $b24->getCRMScope()->contact()->list([], [], ['ID', 'NAME'], 0);

// ✅ Хорошо: все записи, экономия памяти
foreach ($b24->getCRMScope()->contact()->batch->list([], [], ['ID', 'NAME']) as $contact) {
    // обрабатываем по одному
}

// ✅ Хорошо: ровно 200 записей
foreach ($b24->getCRMScope()->contact()->batch->list([], [], ['ID', 'NAME'], 200) as $contact) {
    // обрабатываем по одному, SDK остановится на 200-м
}
```

---

## 8. Шпаргалка: от документации к коду

### Алгоритм работы с новым методом API

1. **Найдите метод в документации** (`b24-rest-docs-main/api-reference/...`)
2. **Запомните имя метода** (`tasks.task.list`, `crm.contact.list`, ...)
3. **Найдите scope** — указан в начале документации (`Scope: task`, `Scope: crm`)
4. **Найдите PHP-класс** через поиск по имени метода в коде SDK:
   ```
   b24phpsdk-3/src/Services/Task/Service/Batch.php → tasks.task.list
   b24phpsdk-3/src/Services/CRM/Contact/Service/Contact.php → crm.contact.list
   ```
5. **Используйте через цепочку вызовов:**

```
$b24
  → get{Scope}Scope()   // getCRMScope(), getTaskScope(), ...
  → {entity}()          // contact(), task(), deal(), ...
  → {method}()          // list(), add(), update(), delete()
  или
  → batch->{method}()   // для больших объёмов данных
```

### Быстрый справочник: scope → builder → метод

| REST scope | Builder-метод | Сервис | Пример |
|-----------|---------------|--------|--------|
| `task` | `getTaskScope()` | `task()` | `$b24->getTaskScope()->task()->list(...)` |
| `crm` | `getCRMScope()` | `contact()` | `$b24->getCRMScope()->contact()->list(...)` |
| `crm` | `getCRMScope()` | `deal()` | `$b24->getCRMScope()->deal()->list(...)` |
| `crm` | `getCRMScope()` | `lead()` | `$b24->getCRMScope()->lead()->list(...)` |
| `user` | `getUserScope()` | `user()` | `$b24->getUserScope()->user()->list(...)` |
| `main` | `getMainScope()` | `main()` | `$b24->getMainScope()->main()->getCurrentUserProfile()` |

### Синтаксис фильтров — универсальный

Работает одинаково для задач и CRM:

```php
$filter = [
    'FIELD'     => 'value',      // = (равно, по умолчанию)
    '!FIELD'    => 'value',      // != (не равно)
    '>FIELD'    => 'value',      // > (больше)
    '>=FIELD'   => 'value',      // >= (больше или равно)
    '<FIELD'    => 'value',      // < (меньше)
    '<=FIELD'   => 'value',      // <= (меньше или равно)
    '%FIELD'    => 'value',      // LIKE %value% (содержит)
    '@FIELD'    => [1, 2, 3],    // IN (одно из значений)
    '!@FIELD'   => [1, 2, 3],    // NOT IN
];
```

### Работа с датами

Битрикс принимает даты в формате `Y-m-d` или `Y-m-d H:i:s`:

```php
// Получить задачи, созданные сегодня
$filter = ['>=CREATED_DATE' => date('Y-m-d')];

// Получить контакты, изменённые за последние 7 дней
$filter = ['>=DATE_MODIFY' => date('Y-m-d', strtotime('-7 days'))];

// Диапазон дат
$filter = [
    '>=DATE_MODIFY' => '2025-01-01',
    '<=DATE_MODIFY' => '2025-01-31',
];
```

---

## Полный рабочий пример

```php
<?php
declare(strict_types=1);

use Bitrix24\SDK\Services\ServiceBuilderFactory;

require_once 'vendor/autoload.php';

$b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook(
    'https://your-portal.bitrix24.com/rest/1/abc123xyz/'
);

// ─── Задачи ────────────────────────────────────────────────
echo "=== Задачи исполнителя 42, созданные с 2025-01-01 ===\n";

foreach ($b24->getTaskScope()->task()->batch->list(
    order: ['CREATED_DATE' => 'desc'],
    filter: [
        '>=CREATED_DATE' => '2025-01-01',
        'RESPONSIBLE_ID' => 42,
    ],
    select: ['ID', 'TITLE', 'CREATED_DATE', 'STATUS', 'DEADLINE']
) as $task) {
    $deadline = $task->deadline?->format('Y-m-d') ?? 'нет';
    echo "#{$task->id} [{$task->status}] {$task->title} (дедлайн: {$deadline})\n";
}

// ─── Контакты ──────────────────────────────────────────────
echo "\n=== Последние 200 изменённых контактов ===\n";

foreach ($b24->getCRMScope()->contact()->batch->list(
    order: ['DATE_MODIFY' => 'DESC'],
    filter: ['>=DATE_MODIFY' => '2025-02-01'],
    select: ['ID', 'NAME', 'LAST_NAME', 'DATE_MODIFY', 'PHONE'],
    limit: 200
) as $contact) {
    echo "#{$contact->ID}: {$contact->NAME} {$contact->LAST_NAME}\n";
}
```
