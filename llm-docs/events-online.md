# Онлайн-события и EventLog

## Оглавление

1. [Онлайн-события: RemoteEventsFactory](#1-онлайн-события-remoteevents-factory)
2. [Поддерживаемые типы событий](#2-поддерживаемые-типы-событий)
3. [EventLog — журнал аудита](#3-eventlog--журнал-аудита)

---

## 1. Онлайн-события: RemoteEventsFactory

**Онлайн-событие** — это HTTP POST, который Битрикс24 отправляет на URL вашего приложения в реальном времени, когда что-то происходит (создана сделка, изменился контакт, новое сообщение в открытой линии и т.д.).

В отличие от офлайн-событий (см. `offline-events.md`), онлайн-события требуют публично доступного URL и работают без OAuth — можно использовать вебхук.

### Регистрация обработчика события

```php
// Подписка на событие
$b24->core->call('event.bind', [
    'event'   => 'ONCRMCONTACTADD',
    'handler' => 'https://your-app.example.com/handler.php',
]);
```

### Обработка входящего события (handler.php)

```php
use Bitrix24\SDK\Services\RemoteEventsFactory;
use Symfony\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();

// 1. Проверить, что запрос можно обработать (правильный Content-Type и payload)
if (!RemoteEventsFactory::isCanProcess($request)) {
    http_response_code(400);
    exit;
}

// 2. Создать объект события из входящего запроса
$factory = RemoteEventsFactory::init();
$event   = $factory->create($request);

// Теперь $event реализует EventInterface:
// $event->getEventName()    → строка, например 'ONCRMCONTACTADD'
// $event->getEventPayload() → массив данных события
// $event->getAuth()         → данные авторизации (domain, client_endpoint, ...)

$eventName = $event->getEventName();
$payload   = $event->getEventPayload();
```

### Проверка подписи (безопасность)

Перед обработкой убедитесь, что запрос действительно пришёл от Битрикс24:

```php
use Bitrix24\SDK\Core\Contracts\Bitrix24AccountInterface;

// $b24Account — ваш объект, реализующий Bitrix24AccountInterface
// (обычно хранит domain, client_id, client_secret)
try {
    $factory->validate($b24Account, $event);
    // подпись верна — обрабатываем
} catch (\Bitrix24\SDK\Core\Exceptions\WrongSecuritySignatureException $e) {
    // подпись не совпала — отклоняем запрос
    http_response_code(403);
    exit;
}
```

### Инициализация RemoteEventsFactory

`RemoteEventsFactory::init()` регистрирует фабрики для всех поддерживаемых типов событий:

```php
$factory = RemoteEventsFactory::init();
// Внутри регистрируются:
// - ApplicationLifeCycleEventsFactory (установка/удаление приложения)
// - TelephonyEventsFactory            (звонки)
// - CalendarEventsFactory             (события календаря)
// - CrmCompanyEventsFactory           (компании CRM)
// - CrmContactEventsFactory           (контакты CRM)
// - IMOpenLinesEventsFactory          (открытые линии)
// - SonetGroupEventsFactory           (социальная сеть)
// - SaleEventsFactory                 (интернет-магазин)
```

---

## 2. Поддерживаемые типы событий

### Жизненный цикл приложения

| Событие | Описание |
|---|---|
| `ONAPPINSTALL` | Приложение установлено |
| `ONAPPUNINSTALL` | Приложение удалено |
| `ONAPPPAYMENT` | Оплата приложения |

### CRM — Контакты

| Событие | Описание |
|---|---|
| `ONCRMCONTACTADD` | Создан контакт |
| `ONCRMCONTACTUPDATE` | Обновлён контакт |
| `ONCRMCONTACTDELETE` | Удалён контакт |

### CRM — Компании

| Событие | Описание |
|---|---|
| `ONCRMCOMPANYADD` | Создана компания |
| `ONCRMCOMPANYUPDATE` | Обновлена компания |
| `ONCRMCOMPANYDELETE` | Удалена компания |

### Открытые линии (imopenlines)

| Событие | Описание |
|---|---|
| `OnOpenLineSessionStart` | Начата сессия |
| `OnOpenLineSessionFinish` | Завершена сессия |
| `OnOpenLineMessageAdd` | Новое сообщение |
| `OnOpenLineMessageUpdate` | Сообщение изменено |
| `OnOpenLineMessageDelete` | Сообщение удалено |
| `ImConnectorMessageAdd` | Входящее от коннектора |
| `ImConnectorMessageUpdate` | Изменение от коннектора |
| `ImConnectorMessageDelete` | Удаление от коннектора |
| `ImConnectorStatusDelivery` | Статус доставки |

### Телефония

| Событие | Описание |
|---|---|
| `ONEXTERNALCALLSTART` | Начало внешнего звонка |
| `ONEXTERNALCALLBACKSTART` | Начало обратного звонка |

### Социальная сеть (sonet_group)

| Событие | Описание |
|---|---|
| `OnSonetGroupAdd` | Создана группа/проект |
| `OnSonetGroupUpdate` | Обновлена группа/проект |
| `OnSonetGroupDelete` | Удалена группа/проект |

### Пример обработки по типу события

```php
$event = $factory->create($request);

switch ($event->getEventName()) {
    case 'ONCRMCONTACTADD':
        $payload = $event->getEventPayload();
        $contactId = $payload['data']['FIELDS']['ID'] ?? null;
        // синхронизируем контакт
        break;

    case 'OnOpenLineMessageAdd':
        $payload = $event->getEventPayload();
        $message = $payload['data']['PARAMS']['MESSAGE'] ?? '';
        // обрабатываем входящее сообщение
        break;
}

// Всегда возвращаем 200, чтобы Битрикс24 не повторял запрос
http_response_code(200);
echo json_encode(['result' => 'ok']);
```

---

## 3. EventLog — журнал аудита

**EventLog** — сервис scope `main`, работает только через REST 3.0, доступен только администратору портала. Позволяет читать журнал аудита (лог событий безопасности).

### Доступ к сервису

```php
$eventLog = $b24->getMainScope()->eventLog();
```

### Получить одну запись

```php
use Bitrix24\SDK\Services\Main\Service\EventLogSelectBuilder;

$select = (new EventLogSelectBuilder())
    ->timestampX()
    ->severity()
    ->userId()
    ->description();

$entry = $eventLog->get(id: 123, select: $select)->eventLogItem();
echo $entry->id;
echo $entry->severity;
echo $entry->description;
```

### Получить список (одна страница)

```php
use Bitrix24\SDK\Services\Main\Service\EventLogFilter;
use Bitrix24\SDK\Services\Main\Service\EventLogSelectBuilder;
use Bitrix24\SDK\Core\Contracts\SortOrder;

$select = (new EventLogSelectBuilder())->allSystemFields();

$filter = (new EventLogFilter())
    ->severity()->eq('SECURITY')
    ->userId()->eq(5);

$result = $eventLog->list(
    select:     $select,
    filter:     $filter,
    order:      ['id' => SortOrder::Descending],
    pagination: ['limit' => 50]
);

foreach ($result->getEventLogItems() as $entry) {
    echo $entry->id . ': ' . $entry->description . PHP_EOL;
}
```

### Tail — непрерывный опрос новых записей

`tail()` предназначен для инкрементальной синхронизации: возвращает только записи, появившиеся после курсора.

```php
use Bitrix24\SDK\Services\Main\Service\EventLogTailCursor;
use Bitrix24\SDK\Core\Contracts\SortOrder;

// Начинаем с ID=0 (все записи)
$cursor = new EventLogTailCursor(
    value: 0,             // значение курсора (ID последней обработанной записи)
    field: 'id',          // поле курсора
    order: SortOrder::Ascending,
    limit: 50             // записей за один запрос
);

$result = $eventLog->tail(
    select:             (new EventLogSelectBuilder())->allSystemFields(),
    filter:             new EventLogFilter(),
    eventLogTailCursor: $cursor
);

foreach ($result->getEventLogItems() as $entry) {
    echo $entry->id . PHP_EOL;
    // Обновляем курсор после обработки
    $cursor = new EventLogTailCursor(value: $entry->id, limit: 50);
}
// Сохраняем $cursor->value в хранилище для следующего запуска
```

### EventLogFilter — поля фильтра

```php
$filter = (new EventLogFilter())
    ->id()->gte(100)
    ->timestampX()->gt(new DateTime('2025-01-01'))
    ->severity()->eq('SECURITY')      // уровень: INFO, SECURITY, WARNING
    ->auditTypeId()->eq('USER_LOGIN')
    ->moduleId()->eq('crm')
    ->itemId()->eq('123')
    ->userId()->eq(5)
    ->guestId()->eq(0)
    ->remoteAddr()->eq('192.168.1.1')
    ->siteId()->eq('s1');
```

### EventLogSelectBuilder — доступные поля

`id` **включается автоматически** (в конструкторе), метода `id()` нет.

| Метод | Поле |
|---|---|
| *(конструктор)* | id записи |
| `timestampX()` | дата/время события |
| `severity()` | уровень (INFO / SECURITY / WARNING) |
| `auditTypeId()` | тип события (USER_LOGIN и т.д.) |
| `moduleId()` | модуль |
| `itemId()` | ID объекта |
| `remoteAddr()` | IP адрес (`Darsyn\IP\Version\Multi\|null`) |
| `userAgent()` | User-Agent браузера |
| `requestUri()` | URI запроса |
| `siteId()` | сайт |
| `userId()` | ID пользователя |
| `guestId()` | ID гостя |
| `description()` | описание |
| `allSystemFields()` | все поля выше |

```php
// Только нужные поля
$select = (new EventLogSelectBuilder())
    ->timestampX()
    ->severity()
    ->userId()
    ->description();

// Все поля
$select = (new EventLogSelectBuilder())->allSystemFields();

// С пользовательскими полями
$select = (new EventLogSelectBuilder())
    ->allSystemFields()
    ->withUserFields(['UF_MY_FIELD']);
```

Подробная документация по всем методам, фильтрам и курсору — см. [main-eventlog.md](main-eventlog.md).
