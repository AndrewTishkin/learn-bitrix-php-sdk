# Bitrix24 PHP SDK v3 — Офлайн-события

Общая информация об OAuth-авторизации — в [sdk-core.md](sdk-core.md).

## Оглавление

1. [Почему вебхук не подходит](#1-почему-вебхук-не-подходит)
2. [Создание локального приложения](#2-создание-локального-приложения)
3. [install.php — первичная настройка](#3-installphp--первичная-настройка)
4. [Подписка на офлайн-события](#4-подписка-на-офлайн-события)
5. [Структура payload события](#5-структура-payload-события)
6. [Цикл синхронизации](#6-цикл-синхронизации)
7. [Фильтрация по воронке](#7-фильтрация-по-воронке)
8. [Предотвращение цикла (auth_connector)](#8-предотвращение-цикла-auth_connector)
9. [Гибридный режим ONOFFLINEEVENT](#9-гибридный-режим-onofflineevent)
10. [Итоговые правила и чек-лист](#10-итоговые-правила-и-чек-лист)

---

## 1. Почему вебхук не подходит

Все методы офлайн-событий (`event.bind` с `event_type=offline`, `event.offline.get`, `event.offline.list`, `event.offline.clear`) работают **только в контексте OAuth-приложения**.

**Причина:** очередь офлайн-событий привязана к конкретному **приложению** (`client_id`). Вебхук — это постоянный access-token одного пользователя, он не несёт понятия "приложение". У вебхука нет `client_id`, и Битрикс24 не знает, куда складывать офлайн-события.

---

## 2. Создание локального приложения

Одноразовая настройка в интерфейсе Битрикс24:

```
Битрикс24 → Разработчикам → Другое → Локальное приложение → Создать
```

- **Тип**: Серверное
- **Использует только API**: включить (нет UI)
- **Путь для первоначальной установки**: `https://your-app.example.com/install.php`
- **Права**: выбрать нужные scope (`crm`, `task`, и т.д.)

После сохранения Битрикс24 сразу делает POST на `install.php` с OAuth-токенами. Приложение без UI не требует вызова `installFinish` — установка завершается автоматически.

---

## 3. install.php — первичная настройка

```php
// Битрикс24 делает POST на этот URL при создании приложения
use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\ApplicationProfile;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Psr\Log\NullLogger;

$auth = $_POST['auth'];

// Сохраняем токены (refresh_token — навсегда)
file_put_contents('tokens.json', json_encode([
    'access_token'    => $auth['access_token'],
    'refresh_token'   => $auth['refresh_token'],
    'client_endpoint' => $auth['client_endpoint'],
    'domain'          => $auth['domain'] ?? '',
]));

$appProfile = ApplicationProfile::initFromArray([
    'BITRIX24_PHP_SDK_APPLICATION_CLIENT_ID'     => 'your_client_id',
    'BITRIX24_PHP_SDK_APPLICATION_CLIENT_SECRET' => 'your_client_secret',
    'BITRIX24_PHP_SDK_APPLICATION_SCOPE'         => 'crm,task',
]);

$factory = new ServiceBuilderFactory(new EventDispatcher(), new NullLogger());

$b24 = $factory->init(
    $appProfile,
    new AuthToken(
        $auth['access_token'],
        $auth['refresh_token'],
        (int)$auth['expires_in']
    ),
    'https://' . $auth['domain'],
    'https://oauth.bitrix.info/'
);

// Регистрируем офлайн-события
$b24->core->call('event.bind', [
    'event'          => 'ONCRMDEALADD',
    'event_type'     => 'offline',
    'auth_connector' => 'my_sync',
]);
$b24->core->call('event.bind', [
    'event'          => 'ONCRMDEALUPDATE',
    'event_type'     => 'offline',
    'auth_connector' => 'my_sync',
]);
$b24->core->call('event.bind', [
    'event'          => 'ONCRMCONTACTADD',
    'event_type'     => 'offline',
    'auth_connector' => 'my_sync',
]);
```

---

## 4. Подписка на офлайн-события

### Параметры event.bind

| Параметр | Онлайн | Офлайн |
|---|---|---|
| `event` | имя события | имя события |
| `handler` | URL обработчика | **не нужен** |
| `event_type` | `online` (по умолчанию) | **`offline`** |
| `auth_connector` | не используется | строка-идентификатор приложения |

### Основные события CRM

| Сущность | Событие | Когда срабатывает |
|---|---|---|
| Сделка | `ONCRMDEALADD` | создана |
| Сделка | `ONCRMDEALUPDATE` | изменена (любое поле) |
| Сделка | `ONCRMDEALMOVETOCAT` | перемещена в другую воронку |
| Сделка | `ONCRMDEALDEL` | удалена |
| Лид | `ONCRMLEADADD` | создан |
| Лид | `ONCRMLEADUPDATE` | изменён |
| Лид | `ONCRMLEADDEL` | удалён |
| Контакт | `ONCRMCONTACTADD` | создан |
| Контакт | `ONCRMCONTACTUPDATE` | изменён |
| Компания | `ONCRMCOMPANYADD` | создана |
| Компания | `ONCRMCOMPANYUPDATE` | изменена |

### Подписка — всегда глобальная

Нет параметра "только воронка N". Битрикс24 добавит в очередь запись о **каждом** обновлении сделки, независимо от воронки. Фильтрация по воронке делается в вашем коде при обработке (см. раздел 7).

---

## 5. Структура payload события

Когда Битрикс24 добавляет запись в офлайн-очередь, он сохраняет **только факт** — какое событие произошло и когда. Данных сущности нет:

```json
{
  "ID": "1",
  "TIMESTAMP_X": "2024-07-18T12:32:31+02:00",
  "EVENT_NAME": "ONCRMDEALUPDATE",
  "EVENT_DATA": false,
  "EVENT_ADDITIONAL": false,
  "MESSAGE_ID": "1"
}
```

`EVENT_DATA = false` — это нормально, так спроектировано намеренно. Даже если сделку обновили 100 раз, в очереди будет одна запись. Актуальное состояние запрашивается через обычный API.

---

## 6. Цикл синхронизации

```
┌─────────────────────────────────────────────────────────┐
│  Цикл синхронизации (например, раз в 5 минут или по     │
│  сигналу ONOFFLINEEVENT)                                │
│                                                         │
│  1. Обновить access_token если истёк (SDK делает сам)   │
│                                                         │
│  2. event.offline.get (clear=0)                         │
│     → получаем пачку до 50 событий + process_id        │
│                                                         │
│  3. Для каждого события:                                │
│     → запросить данные через crm.deal.list / etc.       │
│     → применить логику фильтрации (воронка, стадия…)   │
│     → синхронизировать с внешней системой               │
│     → при ошибке: event.offline.error                   │
│                                                         │
│  4. event.offline.clear (process_id)                   │
│     → подтверждаем успешную обработку                   │
│                                                         │
│  5. Если событий было 50 — повторить с шага 2           │
│     (могут быть ещё в очереди)                          │
└─────────────────────────────────────────────────────────┘
```

```php
// sync.php — запускается по cron или по сигналу ONOFFLINEEVENT
$tokens = json_decode(file_get_contents('tokens.json'), true);

// SDK сам обновит access_token через refresh_token при необходимости
$b24 = $factory->init(
    $appProfile,
    new AuthToken(
        $tokens['access_token'],
        $tokens['refresh_token'],
        0  // 0 = уже истёк, SDK обновит сразу
    ),
    $tokens['client_endpoint'],
    'https://oauth.bitrix.info/'
);

$response = $b24->core->call('event.offline.get', [
    'clear'          => 0,   // не удалять — нужен process_id для подтверждения
    'auth_connector' => 'my_sync',
    'limit'          => 50,
]);

$data      = $response->getResponseData()->getResult()->getData();
$processId = $data['process_id'];
$events    = $data['events'];

if (empty($events)) {
    exit(0);
}

$processedIds = [];

foreach ($events as $event) {
    $eventName = $event['EVENT_NAME'];   // 'ONCRMDEALADD'
    $messageId = $event['MESSAGE_ID'];
    $timestamp = $event['TIMESTAMP_X'];

    try {
        if ($eventName === 'ONCRMDEALADD' || $eventName === 'ONCRMDEALUPDATE') {
            syncDealsChangedSince($b24, $timestamp, targetCategoryId: 5);
        }
        $processedIds[] = $messageId;
    } catch (Throwable $e) {
        $b24->core->call('event.offline.error', [
            'process_id' => $processId,
            'message_id' => [$messageId],
        ]);
    }
}

if (!empty($processedIds)) {
    $b24->core->call('event.offline.clear', [
        'process_id' => $processId,
        'message_id' => $processedIds,
    ]);
}
```

---

## 7. Фильтрация по воронке

Фильтрация по воронке — **в запросе к API**, а не в подписке:

```php
function syncDealsChangedSince($b24, string $timestamp, int $targetCategoryId): void
{
    foreach ($b24->getCRMScope()->deal()->batch->list(
        order:  ['DATE_MODIFY' => 'ASC'],
        filter: [
            '>=DATE_MODIFY' => $timestamp,
            'CATEGORY_ID'   => $targetCategoryId,  // только нужная воронка
        ],
        select: ['ID', 'TITLE', 'STAGE_ID', 'CATEGORY_ID', 'DATE_MODIFY'],
    ) as $deal) {
        sendToExternalSystem($deal);
    }
}
```

### Где узнать ID воронки

```php
$response = $b24->core->call('crm.category.list', ['entityTypeId' => 2]);
// Вернёт: id, name, isDefault для каждой воронки
```

Или в браузере: CRM → Сделки → переключайтесь между воронками, смотрите `categoryId=N` в URL.

### Специальное событие для смены воронки

```php
$b24->core->call('event.bind', [
    'event'          => 'ONCRMDEALMOVETOCAT',
    'event_type'     => 'offline',
    'auth_connector' => 'my_sync',
]);
```

Срабатывает только при смене воронки, а не при любом обновлении.

---

## 8. Предотвращение цикла (auth_connector)

Без защиты возникает цикл:
```
Пользователь меняет сделку
  → офлайн-событие в очереди
    → скрипт читает событие
      → скрипт обновляет сделку
        → офлайн-событие снова в очереди → ∞
```

`auth_connector` разрывает цикл. Когда вы вызываете API **с тем же `auth_connector`**, Битрикс24 не добавляет запись в очередь:

```php
// При регистрации подписки:
// event.bind(event='ONCRMDEALUPDATE', event_type='offline', auth_connector='my_sync')

// При записи изменений из вашего приложения:
$b24->core->call('crm.deal.update', [
    'id'             => $dealId,
    'fields'         => ['STAGE_ID' => 'WON'],
    'auth_connector' => 'my_sync',   // это изменение НЕ попадёт в очередь
]);
```

**Важно:** типизированные методы SDK (`$b24->getCRMScope()->deal()->update(...)`) `auth_connector` **не поддерживают**. Для всех модифицирующих запросов с `auth_connector` используйте `$b24->core->call()`.

**Ограничение:** работает только на тарифе **Pro и выше**.

---

## 9. Гибридный режим ONOFFLINEEVENT

Вместо слепого опроса по cron — получение сигнала от Битрикс24:

```php
// Онлайн-подписка (без event_type=offline) — с URL-обработчиком
$b24->core->call('event.bind', [
    'event'   => 'ONOFFLINEEVENT',
    'handler' => 'https://your-app.example.com/notify.php',
    'options' => ['minTimeout' => 60],  // не чаще 1 раза в 60 секунд
]);
```

```php
// notify.php — вызывается Битрикс24 когда в очереди появились события
$auth = $_POST['auth'];

$b24 = $factory->init(
    $appProfile,
    new AuthToken($auth['access_token'], $auth['refresh_token'], (int)$auth['expires_in']),
    'https://' . $auth['domain'],
    'https://oauth.bitrix.info/'
);

runSyncCycle($b24);
```

Итог: не опрашиваем Битрикс24 вхолостую — он сам сообщает "пора забрать".

---

## 10. Итоговые правила и чек-лист

### Правила фильтрации событий

| Что нужно | Как сделать |
|---|---|
| Только сделки | Подписаться только на `ONCRMDEALADD` / `ONCRMDEALUPDATE` |
| Только лиды | Подписаться только на `ONCRMLEADADD` / `ONCRMLEADUPDATE` |
| Только воронка N | В обработчике запрашивать `crm.deal.list` с `CATEGORY_ID=N` |
| Только смена воронки | Подписаться на `ONCRMDEALMOVETOCAT` |
| Несколько воронок | `'CATEGORY_ID' => [3, 5, 7]` в фильтре |

### Чек-лист реализации

- [ ] `install.php` — принимает OAuth-токены, сохраняет `refresh_token`, вызывает `event.bind`
- [ ] Хранилище токенов с `refresh_token`, `access_token`, `client_endpoint`
- [ ] Цикл синхронизации: `event.offline.get` → обработка → `event.offline.clear`
- [ ] Фильтрация по воронке в обработчике: `crm.deal.list` с `CATEGORY_ID = N`
- [ ] (Опционально) `notify.php` — обработчик `ONOFFLINEEVENT` вместо cron
- [ ] (Опционально, Pro) Передача `auth_connector` в модифицирующие запросы через `core->call()`
