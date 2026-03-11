# main.eventlog.* — Журнал аудита (REST API v3)

## Оглавление

1. [Обзор](#1-обзор)
2. [Доступ к сервису](#2-доступ-к-сервису)
3. [main.eventlog.get](#3-maineventlogget)
4. [main.eventlog.list](#4-maineventloglist)
5. [main.eventlog.tail](#5-maineventlogtail)
6. [EventLogSelectBuilder](#6-eventlogselectbuilder)
7. [EventLogFilter](#7-eventlogfilter)
8. [EventLogTailCursor](#8-eventlogtailcursor)
9. [EventLogItemResult — поля ответа](#9-eventlogitemresult--поля-ответа)
10. [Обработка ошибок](#10-обработка-ошибок)

---

## 1. Обзор

Сервис `EventLog` предоставляет доступ к **журналу аудита Битрикс24** — записям о действиях пользователей: входах, смене паролей, операциях с данными и других системных событиях.

| Параметр | Значение |
|---|---|
| Scope | `main` |
| API версия | REST v3 |
| Доступ | Только администратор портала |
| Batch-операции | Только `list` и `tail`; метод `get` в batch не поддерживается (HTTP 405) |

---

## 2. Доступ к сервису

```php
$eventLog = $b24->getMainScope()->eventLog();
```

---

## 3. main.eventlog.get

Возвращает одну запись журнала по идентификатору.

### Сигнатура

```php
public function get(
    int $id,                                    // положительное целое
    array|EventLogSelectBuilder $select = []    // по умолчанию [] = все поля
): EventLogResult
```

### Пример

```php
use Bitrix24\SDK\Services\Main\Service\EventLogSelectBuilder;

// Выбрать конкретные поля
$select = (new EventLogSelectBuilder())
    ->timestampX()
    ->severity()
    ->auditTypeId()
    ->userId()
    ->description();

$result = $eventLog->get(id: 1250, select: $select);
$entry  = $result->eventLogItem();

echo $entry->id;                                         // int
echo $entry->severity;                                   // string|null
echo $entry->auditTypeId;                                // string|null
echo $entry->userId;                                     // int|null
echo $entry->timestampX?->format('d.m.Y H:i:s');        // CarbonImmutable|null
echo $entry->description;                                // string|null

// Получить IP-адрес (объект Darsyn\IP\Version\Multi)
if ($entry->remoteAddr !== null) {
    echo $entry->remoteAddr->getShortAddress();          // '192.168.1.1' или '::1'
    echo $entry->remoteAddr->getExpandedAddress();       // полный формат
}
```

### Получение результата

```php
$entry = $result->eventLogItem();  // EventLogItemResult
```

> **Важно:** `get()` не поддерживается в batch-запросах — сервер вернёт HTTP 405.

---

## 4. main.eventlog.list

Возвращает список записей журнала с фильтрацией, сортировкой и пагинацией.

### Сигнатура

```php
public function list(
    array|EventLogSelectBuilder  $select     = [],
    array|EventLogFilter         $filter     = [],
    array                        $order      = [],   // ['field' => SortOrder::Ascending]
    array                        $pagination = []    // ['page' => int, 'limit' => int]
): EventLogsResult
```

### Пример — все записи с пагинацией

```php
use Bitrix24\SDK\Services\Main\Service\EventLogSelectBuilder;
use Bitrix24\SDK\Services\Main\Service\EventLogFilter;
use Bitrix24\SDK\Core\Contracts\SortOrder;

$select = (new EventLogSelectBuilder())
    ->timestampX()
    ->severity()
    ->auditTypeId()
    ->userId()
    ->moduleId()
    ->description();

$result = $eventLog->list(
    select:     $select,
    filter:     new EventLogFilter(),
    order:      ['id' => SortOrder::Descending],
    pagination: ['page' => 1, 'limit' => 50]
);

foreach ($result->getEventLogItems() as $entry) {
    echo sprintf(
        "[%s] %s — %s (user:%d)\n",
        $entry->timestampX?->format('d.m.Y H:i'),
        $entry->severity,
        $entry->auditTypeId,
        $entry->userId ?? 0
    );
}
```

### Пример — с фильтром

```php
$filter = (new EventLogFilter())
    ->severity()->eq('SECURITY')
    ->userId()->eq(5)
    ->timestampX()->gte(new \DateTime('2026-01-01'));

$result = $eventLog->list(
    select:     (new EventLogSelectBuilder())->allSystemFields(),
    filter:     $filter,
    order:      ['id' => SortOrder::Ascending],
    pagination: ['limit' => 100]
);
```

### Параметры пагинации

| Параметр | Тип | По умолчанию | Описание |
|---|---|---|---|
| `page` | int | 1 | Номер страницы |
| `limit` | int | 50 | Записей на странице (должен быть > 0) |
| `offset` | int | — | Смещение (вычисляется автоматически из page/limit) |

### Получение результата

```php
$entries = $result->getEventLogItems();  // EventLogItemResult[]
```

---

## 5. main.eventlog.tail

Возвращает только **новые** записи после указанной точки-курсора. Предназначен для инкрементальной синхронизации и мониторинга в реальном времени.

### Сигнатура

```php
public function tail(
    array|EventLogSelectBuilder  $select,              // обязательный
    array|EventLogFilter         $filter,              // обязательный (передать [] или new EventLogFilter() для «без фильтра»)
    EventLogTailCursor           $eventLogTailCursor   // курсор — точка отсчёта
): EventLogsResult
```

### Принцип работы курсора

1. Первый запрос: `value = 0` → вернёт записи начиная с начала (или последние `limit` записей)
2. После обработки: запомнить `id` последней обработанной записи
3. Следующий запрос: `value = <последний id>` → вернёт только записи после этого ID
4. Повторять по расписанию (cron, очередь)

### Пример — разовый вызов

```php
use Bitrix24\SDK\Services\Main\Service\EventLogSelectBuilder;
use Bitrix24\SDK\Services\Main\Service\EventLogFilter;
use Bitrix24\SDK\Services\Main\Service\EventLogTailCursor;
use Bitrix24\SDK\Core\Contracts\SortOrder;

$cursor = new EventLogTailCursor(
    value: 0,                      // начать с начала
    field: 'id',                   // поле курсора
    order: SortOrder::Ascending,   // порядок
    limit: 50                      // записей за запрос (максимум сервера: 1000)
);

$result = $eventLog->tail(
    select: (new EventLogSelectBuilder())
        ->timestampX()
        ->severity()
        ->auditTypeId()
        ->userId()
        ->description(),
    filter: new EventLogFilter(),
    eventLogTailCursor: $cursor
);

$entries = $result->getEventLogItems();
```

### Пример — цикл непрерывной синхронизации

```php
// Загружаем последний обработанный ID из хранилища
$lastId = (int)file_get_contents('/tmp/eventlog_cursor.txt');

$cursor = new EventLogTailCursor(value: $lastId, limit: 1000); // сервер отдаёт не более 1000 записей за запрос

$result = $eventLog->tail(
    select: (new EventLogSelectBuilder())->allSystemFields(),
    filter: new EventLogFilter(),
    eventLogTailCursor: $cursor
);

foreach ($result->getEventLogItems() as $entry) {
    // Обрабатываем запись
    processLogEntry($entry);
    // Обновляем курсор
    $lastId = max($lastId, $entry->id);
}

// Сохраняем курсор для следующего запуска
file_put_contents('/tmp/eventlog_cursor.txt', $lastId);
```

> **Ограничение:** поле курсора нельзя одновременно использовать в `$filter` — API вернёт `BITRIX_REST_V3_EXCEPTION_INVALIDFILTEREXCEPTION`.

### Получение результата

```php
$entries = $result->getEventLogItems();  // EventLogItemResult[]
```

---

## 6. EventLogSelectBuilder

Типобезопасный построитель списка запрашиваемых полей.

`id` **всегда включается автоматически** (добавляется в конструкторе), явного метода `id()` нет.

```php
use Bitrix24\SDK\Services\Main\Service\EventLogSelectBuilder;

// Только нужные поля
$select = (new EventLogSelectBuilder())
    ->timestampX()
    ->severity()
    ->auditTypeId()
    ->userId()
    ->description();

// Все системные поля сразу
$select = (new EventLogSelectBuilder())->allSystemFields();

// Системные + пользовательские (UF_*) поля
$select = (new EventLogSelectBuilder())
    ->allSystemFields()
    ->withUserFields(['UF_MY_FIELD']);
```

### Доступные методы

| Метод | Поле API | Тип в результате |
|---|---|---|
| *(в конструкторе)* | `id` | `int` |
| `timestampX()` | `timestampX` | `CarbonImmutable\|null` |
| `severity()` | `severity` | `string\|null` |
| `auditTypeId()` | `auditTypeId` | `string\|null` |
| `moduleId()` | `moduleId` | `string\|null` |
| `itemId()` | `itemId` | `string\|null` |
| `remoteAddr()` | `remoteAddr` | `Darsyn\IP\Version\Multi\|null` |
| `userAgent()` | `userAgent` | `string\|null` |
| `requestUri()` | `requestUri` | `string\|null` |
| `siteId()` | `siteId` | `string\|null` |
| `userId()` | `userId` | `int\|null` |
| `guestId()` | `guestId` | `int\|null` |
| `description()` | `description` | `string\|null` |
| `allSystemFields()` | все выше | — |
| `withUserFields(array)` | UF_* поля | — |

---

## 7. EventLogFilter

Типобезопасный построитель фильтра (REST 3.0).

```php
use Bitrix24\SDK\Services\Main\Service\EventLogFilter;

$filter = (new EventLogFilter())
    ->severity()->eq('SECURITY')
    ->auditTypeId()->eq('USER_AUTHORIZE')
    ->userId()->eq(5)
    ->guestId()->eq(0)
    ->moduleId()->eq('main')
    ->itemId()->eq('1463')
    ->remoteAddr()->eq('192.168.1.1')
    ->siteId()->eq('s1')
    ->timestampX()->gte(new \DateTime('2026-01-01'))
    ->id()->gte(100);
```

### Доступные поля и типы условий

| Метод фильтра | Тип Builder | Доступные операторы |
|---|---|---|
| `id()` | `IntFieldConditionBuilder` | `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `in`, `between` |
| `userId()` | `IntFieldConditionBuilder` | то же |
| `guestId()` | `IntFieldConditionBuilder` | то же |
| `timestampX()` | `DateTimeFieldConditionBuilder` | `eq`, `neq`, `gt`, `gte`, `lt`, `lte` |
| `severity()` | `StringFieldConditionBuilder` | `eq`, `neq`, `in` |
| `auditTypeId()` | `StringFieldConditionBuilder` | то же |
| `moduleId()` | `StringFieldConditionBuilder` | то же |
| `itemId()` | `StringFieldConditionBuilder` | то же |
| `remoteAddr()` | `StringFieldConditionBuilder` | то же |
| `siteId()` | `StringFieldConditionBuilder` | то же |

### OR-логика

```php
$filter = (new EventLogFilter())
    ->or(function (EventLogFilter $f) {
        $f->severity()->eq('SECURITY');
        $f->severity()->eq('WARNING');
    });
```

### Сырой массив (запасной вариант)

```php
$filter = (new EventLogFilter())
    ->raw([
        ['severity', '=', 'SECURITY'],
        ['userId',   '>', 0],
    ]);
```

> Подробнее о синтаксисе фильтров REST 3.0 см. [filters-rest3.md](filters-rest3.md).

---

## 8. EventLogTailCursor

Неизменяемый объект-курсор для метода `tail()`.

```php
use Bitrix24\SDK\Services\Main\Service\EventLogTailCursor;
use Bitrix24\SDK\Core\Contracts\SortOrder;

$cursor = new EventLogTailCursor(
    value: 0,                      // значение курсора (ID последней обработанной записи)
    field: 'id',                   // поле для сравнения (по умолчанию 'id')
    order: SortOrder::Ascending,   // направление (по умолчанию ASC)
    limit: 50                      // максимум записей за запрос (по умолчанию 50)
);

// Сериализация в массив (для передачи в API)
$array = $cursor->toArray();
// ['field' => 'id', 'order' => 'ASC', 'value' => 0, 'limit' => 50]
```

| Параметр | Тип | По умолчанию | Описание |
|---|---|---|---|
| `value` | int | — | Значение курсора (обязательный) |
| `field` | string | `'id'` | Поле курсора |
| `order` | `SortOrder` | `Ascending` | Направление обхода |
| `limit` | int | `50` | Записей за один запрос (максимум: **1000** — экспериментально подтверждено) |

---

## 9. EventLogItemResult — поля ответа

```php
/** @var EventLogItemResult $entry */
$entry->id;           // int            — идентификатор записи
$entry->timestampX;   // CarbonImmutable|null — дата и время события
$entry->severity;     // string|null    — уровень: 'INFO', 'SECURITY', 'WARNING'
$entry->auditTypeId;  // string|null    — тип события: 'USER_AUTHORIZE', 'USER_LOGOUT', ...
$entry->moduleId;     // string|null    — модуль: 'main', 'crm', ...
$entry->itemId;       // string|null    — идентификатор объекта-причины события
$entry->remoteAddr;   // Darsyn\IP\Version\Multi|null — IP-адрес запроса
$entry->userAgent;    // string|null    — User-Agent браузера/клиента
$entry->requestUri;   // string|null    — URI запроса
$entry->siteId;       // string|null    — сайт ('s1', ...)
$entry->userId;       // int|null       — ID пользователя
$entry->guestId;      // int|null       — ID гостя
$entry->description;  // string|null    — JSON с деталями события
```

### Работа с remoteAddr (Darsyn\IP\Version\Multi)

Поле `remoteAddr` — не строка, а объект с дополнительными возможностями:

```php
use Darsyn\IP\Version\Multi;

$ip = $entry->remoteAddr;  // Multi|null
if ($ip !== null) {
    echo $ip->getShortAddress();       // '192.168.1.1' или '::1' (сжатый формат)
    echo $ip->getExpandedAddress();    // полный IPv6-формат
    echo (string) $ip;                 // строковое представление

    // Проверка CIDR-диапазона
    $cidr = \Darsyn\IP\Version\Multi::factory('10.0.0.0');
    $ip->inRange($cidr, 8);            // true если 10.x.x.x
}
```

### Работа с description (JSON)

`description` содержит JSON-строку с деталями события:

```php
$details = json_decode($entry->description ?? '{}', true);
// Структура зависит от типа события (auditTypeId)
```

---

## 10. Обработка ошибок

| Код ошибки API | Причина | Рекомендация |
|---|---|---|
| `BITRIX_REST_V3_EXCEPTION_ACCESSDENIEDEXCEPTION` | Нет прав администратора или отсутствует scope `main` | Проверить права пользователя и scope вебхука/приложения |
| `BITRIX_REST_V3_EXCEPTION_ENTITYNOTFOUNDEXCEPTION` | Запись с таким ID не найдена (для `get`) | Проверить корректность ID |
| `BITRIX_REST_V3_EXCEPTION_INVALIDPAGINATIONEXCEPTION` | `limit` <= 0 или нечисловое значение | Передать валидный `limit` |
| `BITRIX_REST_V3_EXCEPTION_INVALIDFILTEREXCEPTION` | Поле курсора используется и в `filter`, и в `cursor` для `tail` | Убрать поле курсора из фильтра |
| HTTP 405 | Попытка вызвать `get()` через batch | Вызывать `get()` только напрямую |
| HTTP 503 | Превышен лимит запросов | Подождать и повторить |

```php
use Bitrix24\SDK\Core\Exceptions\BaseException;
use Bitrix24\SDK\Core\Exceptions\TransportException;

try {
    $result = $eventLog->get(1250);
    $entry  = $result->eventLogItem();
} catch (BaseException $e) {
    // Ошибки API (доступ, не найдено, пагинация и т.д.)
    $this->logger->error('EventLog API error', [
        'message' => $e->getMessage(),
        'code'    => $e->getCode(),
    ]);
} catch (TransportException $e) {
    // HTTP / сетевые ошибки
    $this->logger->error('EventLog transport error', ['message' => $e->getMessage()]);
}
```
