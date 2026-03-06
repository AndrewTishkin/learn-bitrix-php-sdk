# SonetGroup scope (sonet_group / socialnetwork)

## Оглавление

1. [Обзор](#1-обзор)
2. [Доступ к сервису](#2-доступ-к-сервису)
3. [Методы SonetGroup](#3-методы-sonetgroup)
4. [События](#4-события)

---

## 1. Обзор

SonetGroup — сервис для работы с **группами и проектами** социальной сети Битрикс24.

В SDK используются два API-пространства:

| Пространство | REST-методы | Назначение |
|---|---|---|
| `sonet_group` | `sonet_group.*` | Устаревшее, но ещё работает |
| `socialnetwork` | `socialnetwork.api.workgroup.*` | Актуальное REST 3.0 |

Scope для прав приложения/вебхука: `sonet_group`

---

## 2. Доступ к сервису

```php
$sonetGroup = $b24->getSonetGroupScope()->sonetGroup();
```

---

## 3. Методы SonetGroup

### Создать группу

```php
$id = $sonetGroup->create([
    'NAME'        => 'Проект Alpha',
    'DESCRIPTION' => 'Описание проекта',
    'VISIBLE'     => 'Y',       // видимость
    'OPENED'      => 'N',       // закрытая группа
    'PROJECT'     => 'N',       // 'Y' = проект (с дедлайном)
    'OWNER_ID'    => 1,
])->getId();
```

### Обновить группу

```php
$sonetGroup->update($groupId, [
    'NAME'        => 'Переименованный проект',
    'DESCRIPTION' => 'Новое описание',
]);
```

### Удалить группу

```php
$sonetGroup->delete($groupId);
```

### Получить группу (REST 3.0)

Метод `get()` использует `socialnetwork.api.workgroup.get`:

```php
$group = $sonetGroup->get($groupId)->group();

echo $group->id;
echo $group->name;
echo $group->description;
echo $group->ownerId;
echo $group->dateActivity->format('d.m.Y'); // CarbonImmutable
```

### Список групп (REST 3.0)

Метод `list()` использует `socialnetwork.api.workgroup.list`:

```php
$result = $sonetGroup->list(
    select: ['ID', 'NAME', 'DESCRIPTION', 'OWNER_ID'],
    filter: ['ACTIVE' => 'Y'],
    order:  ['DATE_ACTIVITY' => 'DESC'],
    pagination: ['limit' => 50]
);

foreach ($result->groups() as $group) {
    echo $group->id . ': ' . $group->name . PHP_EOL;
}
```

### Список групп (устаревший метод)

`getGroups()` использует старый `sonet_group.get` — возвращает сырые данные:

```php
$result = $sonetGroup->getGroups(
    filter: ['ACTIVE' => 'Y'],
    select: ['ID', 'NAME'],
    order:  ['ID' => 'ASC'],
    start:  0
);
// $result->getCoreResponse()->getResponseData()->getResult()
```

### Группы пользователя

```php
$result = $sonetGroup->getUserGroups(
    userId: 5,
    filter: [],
    select: ['ID', 'NAME'],
    order:  ['ID' => 'ASC'],
    start:  0
);
```

### Добавить участника в группу

```php
// Одного пользователя
$sonetGroup->addUser(groupId: 10, userId: 5);

// Нескольких пользователей
$sonetGroup->addUser(groupId: 10, userId: [5, 6, 7]);
```

### Удалить участника из группы

```php
// Одного пользователя
$sonetGroup->deleteUser(groupId: 10, userId: 5);

// Нескольких пользователей
$sonetGroup->deleteUser(groupId: 10, userId: [5, 6, 7]);
```

### Сменить владельца

```php
$sonetGroup->setOwner(groupId: 10, userId: 3);
```

---

## 4. События

Подписка на события групп:

```php
$b24->core->call('event.bind', [
    'event'   => 'OnSonetGroupAdd',
    'handler' => 'https://your-app.example.com/handler.php',
]);
```

| Событие | Описание |
|---|---|
| `OnSonetGroupAdd` | Создана группа/проект |
| `OnSonetGroupUpdate` | Обновлена группа/проект |
| `OnSonetGroupDelete` | Удалена группа/проект |

Обработка события (через RemoteEventsFactory — см. `events-online.md`):

```php
$event = $factory->create($request);
// $event->getEventName() === 'OnSonetGroupAdd'
$payload = $event->getEventPayload();
$groupId = $payload['data']['GROUP_ID'] ?? null;
```
