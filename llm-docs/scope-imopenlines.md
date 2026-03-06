# Bitrix24 PHP SDK v3 — imopenlines scope

Общая информация об SDK: авторизация, batch, фильтры — в [sdk-core.md](sdk-core.md).

## Оглавление

1. [Сервисы imopenlines scope](#1-сервисы-imopenlines-scope)
2. [Config — управление открытыми линиями](#2-config--управление-открытыми-линиями)
3. [Session — диалоги и сессии](#3-session--диалоги-и-сессии)
4. [Operator — действия оператора](#4-operator--действия-оператора)
5. [Message — сообщения](#5-message--сообщения)
6. [CRMChat — чаты CRM-сущностей](#6-crmchat--чаты-crm-сущностей)
7. [Bot — чат-бот в открытой линии](#7-bot--чат-бот-в-открытой-линии)
8. [Network — внешние открытые линии](#8-network--внешние-открытые-линии)
9. [Connector — внешние коннекторы](#9-connector--внешние-коннекторы)
10. [События (Events)](#10-события-events)

---

## 1. Сервисы imopenlines scope

```php
$ol = $b24->getIMOpenLinesScope();
```

| Метод | Сервис | Описание |
|---|---|---|
| `config()` | `Config` | Управление открытыми линиями (CRUD) |
| `session()` | `Session` | Диалоги: открытие, закрытие, история, управление |
| `operator()` | `Operator` | Действия оператора над диалогом |
| `message()` | `Message` | Сообщения в открытой линии |
| `crmChat()` | `Chat` | Чаты, привязанные к CRM-сущностям |
| `bot()` | `Bot` | Чат-бот: отправка сообщений, перевод, завершение |
| `Network()` | `Network` | Внешние открытые линии по коду |
| `connector()` | `Connector` | Внешние коннекторы (регистрация, сообщения, статусы) |

---

## 2. Config — управление открытыми линиями

```php
$config = $b24->getIMOpenLinesScope()->config();
```

### Создать открытую линию

```php
$lineId = $config->add([
    'LINE_NAME'          => 'Поддержка',
    'ACTIVE'             => true,
    'CRM'                => true,
    'QUEUE_TYPE'         => 'round_robin',
    'WELCOME_BOT_ENABLE' => false,
    'LANGUAGE_ID'        => 'ru',
])->getId();
```

### Получить линию

```php
$line = $config->get(
    configId:    $lineId,
    withQueue:   true,   // включить информацию об очереди
    showOffline: true    // показать офлайн-операторов
);
```

### Список всех линий

```php
$lines = $config->getList(
    select: ['ID', 'LINE_NAME', 'ACTIVE'],
    order:  ['LINE_NAME' => 'ASC'],
    filter: ['ACTIVE' => true],
);
```

### Обновить линию

```php
$config->update(id: $lineId, params: [
    'LINE_NAME' => 'Техническая поддержка',
    'ACTIVE'    => true,
]);
```

### Удалить линию

```php
$config->delete(configId: $lineId);
```

### Публичная ссылка на страницу открытых линий

```php
$path = $config->getPath()->getPath();
```

### Информация о ревизиях API

```php
$revision = $config->getRevision();
```

### Подключить внешнюю линию по коду

```php
$config->joinNetwork(code: 'abc123');
```

---

## 3. Session — диалоги и сессии

```php
$session = $b24->getIMOpenLinesScope()->session();
```

### Открыть диалог по символьному коду

```php
$result = $session->open(userCode: 'chat_user_code_abc');
```

### Получить информацию о диалоге

Хотя бы один из параметров обязателен:

```php
$dialog = $session->getDialog(
    chatId:    123,      // ID чата
    dialogId:  null,     // ID диалога
    sessionId: null,     // ID сессии в открытой линии
    userCode:  null,     // строковый идентификатор пользователя
);
```

### История сообщений диалога

```php
$history = $session->getHistory(chatId: 123, sessionId: 456);
```

### Начать диалог / новую сессию

```php
$session->start(chatId: 123);                               // начать диалог
$session->startMessageSession(chatId: 123, messageId: 789); // новый диалог на основе сообщения
```

### Управление диалогом

```php
$session->join(chatId: 123);              // присоединиться к диалогу
$session->intercept(chatId: 123);         // перехватить диалог от текущего оператора
$session->pin(chatId: 123, activate: true);   // закрепить диалог
$session->pin(chatId: 123, activate: false);  // открепить диалог
$session->pinAll();                       // закрепить все доступные диалоги
$session->unpinAll();                     // открепить все диалоги
$session->setSilent(chatId: 123, activate: true);  // скрытый режим (не получать уведомления)
```

### Оценка оператора руководителем

```php
$session->voteHead(
    sessionId: 456,
    rating:    5,          // 1–5 звёзд
    comment:   'Отлично!'
);
```

### Создать лид из диалога

```php
$session->createCrmLead(chatId: 123);
```

---

## 4. Operator — действия оператора

```php
$operator = $b24->getIMOpenLinesScope()->operator();
```

```php
$operator->answer(chatId: 123);           // взять диалог в работу
$operator->finish(chatId: 123);           // завершить диалог (текущий оператор)
$operator->anotherFinish(chatId: 123);    // завершить диалог другого оператора
$operator->skip(chatId: 123);             // пропустить диалог
$operator->spam(chatId: 123);             // отметить как спам

// Передать другому оператору (по userId)
$operator->transfer(chatId: 123, transferId: 7);

// Передать в другую очередь линии
// transferId в формате "queue#lineId#"
$operator->transfer(chatId: 123, transferId: 'queue#5#');
```

---

## 5. Message — сообщения

```php
$message = $b24->getIMOpenLinesScope()->message();
```

### Отправить сообщение от имени сотрудника или бота (в чат CRM-сущности)

```php
$message->addCrmMessage(
    crmEntityType: 'deal',   // lead|deal|company|contact
    crmEntity:     123,      // ID CRM-сущности
    userId:        5,        // ID сотрудника или бота-отправителя
    chatId:        456,      // ID чата открытой линии
    message:       'Привет! Чем могу помочь?'
);
```

### Сохранить сообщение в быстрые ответы

```php
$message->quickSave(chatId: 456, messageId: 789);
```

### Начать новый диалог на основе сообщения

```php
$message->sessionStart(chatId: 456, messageId: 789);
```

---

## 6. CRMChat — чаты CRM-сущностей

```php
$crmChat = $b24->getIMOpenLinesScope()->crmChat();
```

### Получить чаты, привязанные к CRM-сущности

```php
$chats = $crmChat->get(
    crmEntityType: 'deal',  // lead|deal|company|contact
    crmEntity:     123,     // ID сущности
    activeOnly:    true     // только активные чаты (null = все)
);
```

### Получить ID последнего чата сущности

```php
$chatId = $crmChat->getLastId(
    crmEntityType: 'DEAL',  // LEAD|DEAL|COMPANY|CONTACT (UPPERCASE)
    crmEntity:     123
)->getChatId();
```

### Добавить пользователя в чат

```php
$crmChat->addUser(
    crmEntityType: 'deal',
    crmEntity:     123,
    userId:        7,
    chatId:        456  // null = использовать последний чат сущности
);
```

### Удалить пользователя из чата

```php
$crmChat->deleteUser(
    crmEntityType: 'deal',
    crmEntity:     123,
    userId:        7,
    chatId:        456  // null = использовать последний чат сущности
);
```

---

## 7. Bot — чат-бот в открытой линии

Требует scope `imopenlines` + `imbot`.

```php
$bot = $b24->getIMOpenLinesScope()->bot();
```

```php
// Отправить сообщение от бота
$bot->sendMessage(
    chatId:  123,
    message: 'Добрый день! Чем могу помочь?',
    name:    'WELCOME'  // WELCOME = приветственное, DEFAULT = обычное
);

// Передать свободному оператору
$bot->transferToOperator(chatId: 123);

// Передать конкретному оператору
$bot->transferToUser(
    chatId: 123,
    userId: 7,
    leave:  'N'  // N = бот остаётся в чате до подтверждения, Y = уходит сразу
);

// Передать в другую очередь открытой линии
$bot->transferToQueue(
    chatId:  123,
    queueId: 5,
    leave:   'N'
);

// Завершить сессию
$bot->finishSession(chatId: 123);
```

---

## 8. Network — внешние открытые линии

```php
$network = $b24->getIMOpenLinesScope()->Network();
```

### Подключить внешнюю открытую линию по коду

```php
$result = $network->join(openLineCode: 'abc123xyz');
```

### Отправить сообщение пользователю через открытую линию

```php
$result = $network->messageAdd(
    openLineCode:      'abc123xyz',
    recipientUserId:   5,
    message:           'Привет! Ваш заказ готов.',
    isMakeUrlPreview:  true,
    attach:            null,     // вложения (опционально)
    keyboard:          null,     // клавиатура-кнопки (опционально)
);
```

---

## 9. Connector — внешние коннекторы

Используется для интеграции внешних каналов (Telegram, WhatsApp и т.д.) с открытыми линиями.

```php
$connector = $b24->getIMOpenLinesScope()->connector();
```

### Регистрация и управление коннектором

```php
// Зарегистрировать новый коннектор
$connector->register([
    'ID'                => 'my_telegram_bot',
    'NAME'              => 'Telegram Bot',
    'ICON'              => ['DATA_IMAGE' => 'base64...', 'COLOR' => '#2CA5E0'],
    'PLACEMENT_HANDLER' => 'https://app.example.com/placement',
    'DEL_EXTERNAL_MESSAGES' => true,
    'EDIT_INTERNAL_MESSAGES' => true,
]);

// Активировать/деактивировать коннектор для линии
$connector->activate(connector: 'my_telegram_bot', line: '5', active: 1); // 1=вкл, 0=выкл

// Получить статус коннектора
$status = $connector->status(line: '5', connector: 'my_telegram_bot');

// Список всех зарегистрированных коннекторов
$connectors = $connector->list();

// Изменить данные коннектора
$connector->setData(connector: 'my_telegram_bot', line: '5', data: [
    'id'     => 'bot_id',
    'url'    => 'https://t.me/mybot',
    'url_im' => 'https://t.me/mybot',
    'name'   => 'MyBot',
]);

// Изменить название чата
$connector->setChatName(
    connector: 'my_telegram_bot',
    line:      '5',
    chatId:    'external_chat_id',
    name:      'Новое название чата',
    userId:    null   // для не-групповых коннекторов
);

// Отменить регистрацию коннектора
$connector->unregister(id: 'my_telegram_bot');
```

### Управление сообщениями через коннектор

```php
// Структура одного сообщения для отправки
$message = [
    'user' => [
        'id'      => 'external_user_id',
        'name'    => 'Иван Иванов',
        'avatar'  => 'https://example.com/avatar.jpg',
        'gender'  => 'M',  // M|F|U
        'profile' => 'https://t.me/ivanov',
    ],
    'message' => [
        'id'   => 'external_msg_id',
        'date' => time(),
        'text' => 'Привет! Нужна помощь.',
    ],
];

// Отправить сообщения из внешнего канала в Битрикс24
$connector->sendMessages(connector: 'my_telegram_bot', line: '5', messages: [$message]);

// Обновить ранее отправленные сообщения
$connector->updateMessages(connector: 'my_telegram_bot', line: '5', messages: [$message]);

// Удалить отправленные сообщения
$connector->deleteMessages(connector: 'my_telegram_bot', line: '5', messages: [$message]);
```

### Статусы доставки и прочтения

```php
// Обновить статус доставки
$connector->sendStatusDelivery(connector: 'my_telegram_bot', line: '5', messages: [
    ['id' => 'external_msg_id', 'date' => time()],
]);

// Обновить статус прочтения
$connector->sendStatusReading(connector: 'my_telegram_bot', line: '5', messages: [
    ['id' => 'external_msg_id', 'date' => time()],
]);
```

---

## 10. События (Events)

SDK поддерживает 5 событий открытых линий. Их обработка доступна через `IMOpenLinesEventsFactory`.

| Константа | Событие | Когда срабатывает |
|---|---|---|
| `OnSessionStart::CODE` | `ONSESSIONSTART` | Начало нового диалога (пользователь написал первым) |
| `OnSessionFinish::CODE` | `ONSESSIONFINISH` | Завершение диалога |
| `OnOpenLineMessageAdd::CODE` | `ONOPENLINEMESSAGEADD` | Новое сообщение в диалоге |
| `OnOpenLineMessageUpdate::CODE` | `ONOPENLINEMESSAGEUPDATE` | Изменение сообщения |
| `OnOpenLineMessageDelete::CODE` | `ONOPENLINEMESSAGEDELETE` | Удаление сообщения |

### Обработка события OnSessionStart

```php
use Bitrix24\SDK\Services\IMOpenLines\Events\IMOpenLinesEventsFactory;
use Symfony\Component\HttpFoundation\Request;

$factory = new IMOpenLinesEventsFactory();
$event = $factory->create(Request::createFromGlobals());

// OnSessionStartPayload
$payload = $event->getEventPayload();
$data = $payload->data();

$data->connector()->connector_id;  // string — ID коннектора
$data->connector()->line_id;       // int — ID линии
$data->connector()->chat_id;       // int — ID чата в Битрикс24
$data->connector()->user_id;       // int — ID пользователя во внешней системе

$data->chat()->id;                 // int — ID чата
$data->user()->id;                 // int — ID пользователя
$data->user()->name;               // string — имя пользователя
$data->line()->id;                 // int — ID линии
$data->line()->name;               // string — название линии
```

### Обработка события OnOpenLineMessageAdd

```php
$payload = $event->getEventPayload();
$data = $payload->data();

$data->connector()->connector_id;
$data->connector()->line_id;
$data->connector()->chat_id;

$data->message()->id;       // int — ID сообщения
$data->message()->date;     // string — дата/время
$data->message()->text;     // string — текст
$data->message()->files;    // array — прикреплённые файлы
$data->message()->system;   // string — 'Y'|'N', системное ли сообщение
$data->message()->user_id;  // int — ID отправителя

$data->ref()->trackId;      // mixed — трекер-код для привязки к CRM
$data->extra()->EXTRA_URL;  // string — внешняя ссылка (Bitrix24.Network)
```

### Коннектор-события (ImConnector)

SDK также содержит отдельную фабрику событий `ImConnectorEventsFactory` для событий внешних коннекторов:

| Событие | Когда срабатывает |
|---|---|
| `ONIMCONNECTORDIALOGSTART` | Начало диалога через коннектор |
| `ONIMCONNECTORDIALOGFINISH` | Завершение диалога через коннектор |
| `ONIMCONNECTORMESSAGEADD` | Новое сообщение через коннектор |
| `ONIMCONNECTORMESSAGEUPDATE` | Изменение сообщения через коннектор |
| `ONIMCONNECTORMESSAGEDELETE` | Удаление сообщения через коннектор |
| `ONIMCONNECTORSTATUSDELETE` | Удаление статуса коннектора |
