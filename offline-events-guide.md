# Офлайн-события Битрикс24: полное понимание для синхронизации

## Главный нюанс, который не написан явно

**Вебхук не подходит.** Все методы офлайн-событий (`event.bind` с `event_type=offline`, `event.offline.get`, `event.offline.list`, `event.offline.clear`) работают **только в контексте авторизации OAuth-приложения**. Это написано в каждом из методов, но не объяснено почему.

**Причина:** очередь офлайн-событий привязана к конкретному **приложению** (его `client_id`). Вебхук — это просто постоянный access-token одного пользователя, он не несёт понятия "приложение". У вебхука нет `client_id`, и Битрикс не знает, куда складывать офлайн-события.

Значит, первое что нужно сделать — создать **локальное приложение**.

---

## Шаг 0: Создание локального приложения (одноразовая настройка)

Это делается один раз в интерфейсе Битрикс24:

```
Битрикс24 → Разработчикам → Другое → Локальное приложение → Создать
```

Настройки:
- **Тип**: Серверное
- **Использует только API**: включить (у нас нет UI)
- **Путь для первоначальной установки**: `https://your-app.example.com/install.php`
- **Права**: выбрать нужные scope (`crm`, `task`, и т.д.)

После сохранения Битрикс24 немедленно делает POST-запрос на `install.php` с OAuth-токенами:

```json
{
  "event": "ONAPPINSTALL",
  "auth": {
    "access_token":  "s6p6ec...",
    "refresh_token": "4s386p...",
    "client_endpoint": "https://your-portal.bitrix24.ru/rest/",
    "member_id": "a223c6b3710f85df22e9377d6c4f7553"
  }
}
```

**`refresh_token` нужно сохранить навсегда** — он долгоживущий и используется для получения нового `access_token` (который живёт 1 час).

```php
// install.php — вызывается Битрикс24 один раз при создании приложения
$auth = $_POST['auth'];

// Сохраняем в БД или файл
file_put_contents('tokens.json', json_encode([
    'access_token'    => $auth['access_token'],
    'refresh_token'   => $auth['refresh_token'],
    'client_endpoint' => $auth['client_endpoint'],
    'domain'          => $auth['domain'] ?? '',
]));
```

Приложение без UI (`Использует только API`) не требует вызова `installFinish` — установка завершается автоматически после успешного ответа вашего `install.php`.

---

## Шаг 1: Подписка на офлайн-события — что именно здесь настраивается

### Что делает `event.bind` с `event_type=offline`

Вызов `event.bind` регистрирует: "когда произойдёт событие `X`, добавить запись в очередь приложения". Никакой логики фильтрации внутри подписки нет — она просто говорит "я хочу знать о событии такого типа".

```php
// Подписываемся на добавление и изменение сделок
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
```

**Параметры `event.bind` для офлайн-режима:**

| Параметр | Онлайн | Офлайн |
|---|---|---|
| `event` | имя события | имя события |
| `handler` | URL вашего обработчика | **не нужен** |
| `event_type` | `online` (по умолчанию) | **`offline`** |
| `auth_connector` | не используется | строка-идентификатор вашего приложения |

### Какие события существуют

Полный список — в документации в разделе `api-reference/crm/*/events/`. Основные:

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

### Нельзя подписаться только на события одной воронки

Подписка `event.bind` — всегда **глобальная**. Нет параметра "только воронка N" или "только стадия X". Битрикс24 добавит в очередь запись о **каждом** обновлении сделки, независимо от воронки.

Фильтрация по воронке делается **в вашем коде** при обработке событий.

---

## Шаг 2: Как устроен payload события — почему фильтрацию нельзя сделать раньше

Когда Битрикс24 добавляет запись в офлайн-очередь, он сохраняет **только факт** — какое событие произошло и когда. Данных сущности там нет:

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

`EVENT_DATA = false` — это нормально, так спроектировано намеренно. Даже если сделку обновили 100 раз, в очереди будет одна запись. Вы сами запрашиваете актуальное состояние через обычный API.

Поэтому узнать `CATEGORY_ID` (воронку) можно только запросив сделку через `crm.deal.get` или `crm.deal.list`.

---

## Шаг 3: Рабочий цикл синхронизации с фильтрацией по воронке

```
1. event.offline.get (clear=0)
   → получаем пачку до 50 событий + process_id

2. Для каждого события ONCRMDEALUPDATE / ONCRMDEALADD:
   → crm.deal.list с фильтром CATEGORY_ID + >=DATE_MODIFY
   → если CATEGORY_ID совпадает — синхронизировать
   → если нет — просто подтверждаем без обработки

3. event.offline.clear (process_id)
   → подтверждаем успешную обработку

4. Если событий было 50 — повторить с шага 1
```

```php
// sync.php — запускается по cron или по сигналу ONOFFLINEEVENT

$response = $b24->core->call('event.offline.get', [
    'clear'          => 0,
    'auth_connector' => 'my_sync',
    'limit'          => 50,
]);

$data       = $response->getResponseData()->getResult()->getData();
$processId  = $data['process_id'];
$events     = $data['events'];

if (empty($events)) {
    exit(0);
}

$processedIds = [];

foreach ($events as $event) {
    $eventName = $event['EVENT_NAME'];
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

// Фильтрация по воронке происходит здесь — в запросе к API
function syncDealsChangedSince($b24, string $timestamp, int $targetCategoryId): void
{
    foreach ($b24->getCRMScope()->deal()->batch->list(
        order:  ['DATE_MODIFY' => 'ASC'],
        filter: [
            '>=DATE_MODIFY' => $timestamp,
            'CATEGORY_ID'   => $targetCategoryId,  // ← только нужная воронка
        ],
        select: ['ID', 'TITLE', 'STAGE_ID', 'CATEGORY_ID', 'DATE_MODIFY'],
    ) as $deal) {
        sendToExternalSystem($deal);
    }
}
```

### Где узнать ID воронки

```php
// Список всех воронок сделок (CATEGORY_ID)
$response = $b24->core->call('crm.category.list', [
    'entityTypeId' => 2,  // 2 = сделки
]);
// Вернёт: id, name, isDefault для каждой воронки
```

Или в браузере: CRM → Сделки → переключайтесь между воронками и смотрите `categoryId=N` в URL.

### Специальное событие для смены воронки

Если нужно реагировать именно на **перемещение сделки в нужную воронку**, подпишитесь на:

```php
$b24->core->call('event.bind', [
    'event'          => 'ONCRMDEALMOVETOCAT',
    'event_type'     => 'offline',
    'auth_connector' => 'my_sync',
]);
```

Это событие срабатывает только при смене воронки, а не при любом обновлении. Воронку назначения всё равно проверяете через `crm.deal.get`.

---

## Предотвращение бесконечного цикла (`auth_connector`)

Без защиты возникает цикл:
```
Пользователь меняет сделку
  → офлайн-событие в очереди
    → ваш скрипт читает событие
      → ваш скрипт обновляет сделку (crm.deal.update)
        → офлайн-событие снова в очереди
          → ∞
```

`auth_connector` разрывает цикл. Когда вы вызываете API **с тем же `auth_connector`**, Битрикс24 понимает: это изменение сделано тем же приложением, которое подписалось — не добавлять в очередь.

```php
// При регистрации:
// event.bind(event='ONCRMDEALUPDATE', event_type='offline', auth_connector='my_sync')

// При записи изменений из вашего приложения:
$b24->core->call('crm.deal.update', [
    'id'             => $dealId,
    'fields'         => ['STAGE_ID' => 'WON'],
    'auth_connector' => 'my_sync',   // ← это изменение НЕ попадёт в очередь
]);
```

**Ограничение:** `auth_connector` работает только на тарифе **Pro и выше**.

---

## Гибридный режим: ONOFFLINEEVENT (убирает cron)

Вместо слепого опроса по расписанию можно получать сигнал от Битрикс24 о том, что появились новые события. Для этого подписываемся на специальное событие `ONOFFLINEEVENT` **как на обычное онлайн-событие** (с URL-обработчиком):

```php
// Это обычная онлайн-подписка — нет event_type=offline
$b24->core->call('event.bind', [
    'event'   => 'ONOFFLINEEVENT',
    'handler' => 'https://your-app.example.com/notify.php',
    'options' => ['minTimeout' => 60],  // не чаще 1 раза в 60 секунд
]);
```

Когда в очередь офлайн-событий добавляется новая запись, Битрикс24 POST-запросом вызывает ваш `notify.php`. В теле передаётся `access_token`, которым можно сразу вызвать `event.offline.get`.

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

Итог: вы не опрашиваете Битрикс24 вхолостую каждые N минут — он сам сообщает вам "пора забрать".

---

## Полная цепочка зависимостей

```
Битрикс24 (портал)
  │
  ├─ Локальное приложение (client_id + client_secret)
  │   ├─ install.php ← POST от Б24 при создании приложения
  │   │   ├─ получает access_token + refresh_token
  │   │   ├─ сохраняет refresh_token
  │   │   └─ вызывает event.bind (event_type=offline) для нужных событий
  │   │
  │   └─ notify.php ← POST от Б24 когда появились события (ONOFFLINEEVENT)
  │       └─ запускает цикл синхронизации
  │
  └─ Очередь офлайн-событий (на стороне Б24, привязана к client_id)
      ├─ Хранит: EVENT_NAME + TIMESTAMP_X + MESSAGE_ID (не сами данные!)
      └─ Дедупликация: один объект — одна запись, независимо от числа изменений

Ваше приложение
  ├─ Хранит: refresh_token (в БД / файле)
  ├─ Обновляет: access_token через refresh_token при истечении
  └─ Цикл синхронизации:
      1. event.offline.get (clear=0) → [events] + process_id
      2. Для каждого события: crm.deal.list / crm.lead.list с CATEGORY_ID
         (фильтрация по воронке — здесь, в запросе к API, не в подписке)
      3. event.offline.clear (process_id) → подтверждение
      4. Если событий было 50 — повторить (могут быть ещё)
```

---

## Что нужно реализовать — чек-лист

- [ ] `install.php` — принимает OAuth-токены от Б24, сохраняет `refresh_token`, вызывает `event.bind`
- [ ] Хранилище токенов (БД/файл) с `refresh_token`, `access_token`, `client_endpoint`
- [ ] Логика обновления `access_token` через `refresh_token` (SDK делает это автоматически)
- [ ] Цикл синхронизации: `event.offline.get` → обработка → `event.offline.clear`
- [ ] Фильтрация по воронке внутри обработчика: `crm.deal.list` с `CATEGORY_ID = N`
- [ ] (Опционально) `notify.php` — обработчик `ONOFFLINEEVENT` вместо cron
- [ ] (Опционально, Pro тариф) Передача `auth_connector` во все модифицирующие запросы

---

## Итоговые правила фильтрации событий

| Что хочу | Как сделать |
|---|---|
| Только сделки (не лиды) | Подписаться только на `ONCRMDEALADD` / `ONCRMDEALUPDATE` |
| Только лиды | Подписаться только на `ONCRMLEADADD` / `ONCRMLEADUPDATE` |
| Только воронка N | Подписаться на все `ONCRMDEALUPDATE`, в обработчике запрашивать `crm.deal.list` с `CATEGORY_ID=N` |
| Только смена воронки | Подписаться на `ONCRMDEALMOVETOCAT` |
| Несколько воронок | В `crm.deal.list` передать массив: `'CATEGORY_ID' => [3, 5, 7]` |
