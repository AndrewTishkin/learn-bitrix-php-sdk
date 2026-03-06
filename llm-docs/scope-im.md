# Bitrix24 PHP SDK v3 — im scope

Общая информация об SDK: авторизация, batch, фильтры — в [sdk-core.md](sdk-core.md).

## Оглавление

1. [Сервисы im scope](#1-сервисы-im-scope)
2. [Уведомления (Notify)](#2-уведомления-notify)

---

## 1. Сервисы im scope

```php
$im = $b24->getIMScope();
```

| Метод | Сервис | Описание |
|---|---|---|
| `notify()` | `Notify` | Системные и персональные уведомления |

---

## 2. Уведомления (Notify)

```php
$notify = $b24->getIMScope()->notify();
```

### Отправить системное уведомление

Уведомление от имени системы (без конкретного отправителя):

```php
$notifyId = $notify->fromSystem(
    userId:  5,
    message: 'Ваш заказ обработан',
    forEmailChannelMessage: 'Ваш заказ #123 обработан',  // текст для email-канала (опционально)
    notificationTag: 'ORDER_PROCESSED',  // тег для идентификации (опционально)
    subTag:  'order_123',                // подтег (опционально)
    attachment: null                     // вложение (опционально)
)->getId();
```

### Отправить персональное уведомление

Уведомление от имени конкретного пользователя:

```php
$notifyId = $notify->fromPersonal(
    userId:  5,
    message: 'Привет, посмотри задачу',
    forEmailChannelMessage: null,
    notificationTag: null,
    subTag:  null,
    attachment: null
)->getId();
```

### Удалить уведомление

По ID уведомления:

```php
$notify->delete(notificationId: $notifyId);
```

По тегу (удаляет все уведомления с этим тегом):

```php
$notify->delete(
    notificationId: 0,
    notificationTag: 'ORDER_PROCESSED',
    subTag: 'order_123'
);
```

### Отметить уведомления как прочитанные

Одно уведомление:

```php
// isOnlyCurrent=true — только это уведомление
// isOnlyCurrent=false — все уведомления ниже по списку
$notify->markAsRead(notificationId: $notifyId, isOnlyCurrent: true);
```

Список уведомлений (исключая тип CONFIRM):

```php
$notify->markMessagesAsRead(notificationIds: [101, 102, 103]);
$notify->markMessagesAsUnread(notificationIds: [101, 102]);
```

### Ответить на уведомление (quick reply)

```php
$notify->answer(notificationId: $notifyId, answerText: 'Принято');
```

### Подтвердить или отклонить уведомление с кнопками (CONFIRM-тип)

```php
$notify->confirm(notificationId: $notifyId, isAccept: true);   // принять
$notify->confirm(notificationId: $notifyId, isAccept: false);  // отклонить
```

---

### Вложения (attach)

Параметр `attachment` в `fromSystem` / `fromPersonal` принимает массив блоков:

```php
$attachment = [
    [
        'ID'      => '1',
        'COLOR'   => '#FF0000',
        'BLOCKS'  => [
            ['MESSAGE' => 'Текст блока'],
            ['LINK'    => ['LINK' => 'https://example.com', 'NAME' => 'Ссылка']],
        ],
    ],
];

$notify->fromSystem(userId: 5, message: 'Уведомление с вложением', attachment: $attachment);
```
