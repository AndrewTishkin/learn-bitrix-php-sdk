# Bitrix24 PHP SDK v3 — Task scope

Общая информация об SDK: авторизация, batch, фильтры — в [sdk-core.md](sdk-core.md).

## Оглавление

1. [Сервисы Task scope](#1-сервисы-task-scope)
2. [CRUD задач](#2-crud-задач)
3. [TaskItemBuilder — создание полей](#3-taskitembuilder--создание-полей)
4. [Batch-операции](#4-batch-операции)
5. [TaskFilter — типобезопасные фильтры](#5-taskfilter--типобезопасные-фильтры)
6. [TaskItemResult — поля результата](#6-taskitemresult--поля-результата)
7. [Пользовательские поля (UF_)](#7-пользовательские-поля-uf_)
8. [Чеклисты](#8-чеклисты)
9. [Комментарии](#9-комментарии)
10. [Учёт времени](#10-учёт-времени)
11. [Справочник статусов](#11-справочник-статусов)

---

## 1. Сервисы Task scope

```php
$tasks = $b24->getTaskScope();
```

| Метод | Сервис | Описание |
|---|---|---|
| `task()` | `Task` | Задачи: CRUD + batch |
| `taskAccess()` | `TaskAccess` | Проверка прав на задачу |
| `taskChat()` | `TaskChat` | Чат задачи |
| `taskFile()` | `TaskFile` | Файлы задачи |
| `userfield()` | `Userfield` | Пользовательские поля задач |
| `checklistitem()` | `Checklistitem` | Пункты чеклиста |
| `commentitem()` | `Commentitem` | Комментарии к задаче |
| `result()` | `Result` | Результаты задачи |
| `elapseditem()` | `Elapseditem` | Учёт затраченного времени |
| `stage()` | `Stage` | Стадии Kanban |
| `planner()` | `Planner` | Планировщик задач |
| `flow()` | `Flow` | Потоки задач |

---

## 2. CRUD задач

Все методы Task используют **REST API v3** с **camelCase** именами полей.

### Получить задачу

```php
$task = $b24->getTaskScope()->task()->get(123)->task();
echo $task->id;           // int
echo $task->title;        // string
echo $task->responsibleId; // int
echo $task->deadline?->format('d.m.Y');  // CarbonImmutable|null
```

С выбором полей:

```php
$task = $b24->getTaskScope()->task()->get(
    id: 123,
    select: ['id', 'title', 'status', 'deadline', 'responsibleId']
)->task();
```

### Создать задачу

С массивом:

```php
$id = $b24->getTaskScope()->task()->add([
    'title'         => 'Моя задача',
    'responsibleId' => 5,
    'creatorId'     => 1,
    'description'   => 'Описание задачи',
    'deadline'      => '2025-12-31T23:59:59+03:00',
    'groupId'       => 10,  // ID рабочей группы/проекта
])->task()->id;
```

С `TaskItemBuilder`:

```php
use Bitrix24\SDK\Services\Task\Service\TaskItemBuilder;
use Carbon\Carbon;

$id = $b24->getTaskScope()->task()->add(
    (new TaskItemBuilder('Название задачи', creatorId: 1, responsibleId: 5))
        ->description('Описание')
        ->deadline(Carbon::parse('2025-12-31'))
        ->groupId(10)
        ->needsControl(true)
)->task()->id;
```

### Обновить задачу

```php
$b24->getTaskScope()->task()->update(123, [
    'title'  => 'Новое название',
    'status' => 3,  // в работе
]);

// Или через TaskItemBuilder
$b24->getTaskScope()->task()->update(123,
    (new TaskItemBuilder('Новое название', 1, 5))
        ->deadline(Carbon::parse('2026-01-31'))
);
// При update() поле creatorId автоматически исключается из fields
```

### Удалить задачу

```php
$b24->getTaskScope()->task()->delete(123);
```

---

## 3. TaskItemBuilder — создание полей

Fluent-builder для формирования полей задачи.

```php
$builder = new TaskItemBuilder(
    title: 'Название',       // обязательное
    creatorId: 1,            // обязательное
    responsibleId: 5         // обязательное
);

$builder
    ->title('Новое название')
    ->description('Текст описания')
    ->deadline(Carbon::parse('2025-12-31'))    // CarbonInterface → DATE_ATOM
    ->startPlan(Carbon::parse('2025-11-01'))
    ->endPlan(Carbon::parse('2025-12-31'))
    ->groupId(10)       // ID рабочей группы
    ->stageId(3)        // ID стадии Kanban
    ->responsibleId(7)
    ->creatorId(1)
    ->needsControl(true);  // требуется подтверждение выполнения

// Создать builder из существующей задачи
$builder = TaskItemBuilder::createFromTask($existingTaskItemResult);
```

---

## 4. Batch-операции

### Получить все задачи (автопагинация)

```php
foreach ($b24->getTaskScope()->task()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: ['RESPONSIBLE_ID' => 5],
    select: ['id', 'title', 'status', 'deadline', 'groupId'],
    limit:  null  // null = все записи
) as $task) {
    echo $task->id . ': ' . $task->title . PHP_EOL;
}
```

> Внимание: у `batch->list()` фильтр использует **UPPERCASE** имена полей (как в REST API),
> у `task()->get/add/update` — **camelCase**.

### Массовое создание

```php
$tasks = [
    ['title' => 'Задача 1', 'responsibleId' => 5, 'creatorId' => 1],
    ['title' => 'Задача 2', 'responsibleId' => 7, 'creatorId' => 1],
];

foreach ($b24->getTaskScope()->task()->batch->add($tasks) as $result) {
    echo $result->getId() . PHP_EOL;
}
```

### Массовое обновление

```php
$updates = [
    123 => ['fields' => ['status' => 5]],  // завершена
    124 => ['fields' => ['status' => 5]],
];

foreach ($b24->getTaskScope()->task()->batch->update($updates) as $result) {
    // UpdatedTaskBatchResult
}
```

### Массовое удаление

```php
$ids = [123, 124, 125];

foreach ($b24->getTaskScope()->task()->batch->delete($ids) as $result) {
    // DeletedItemBatchResult
}
```

---

## 5. TaskFilter — типобезопасные фильтры

`TaskFilter` — type-safe builder для фильтров. Строит массив для передачи в `batch->list()`.

```php
use Bitrix24\SDK\Services\Task\Service\TaskFilter;

$filter = (new TaskFilter())
    ->responsibleId()->equals(5)
    ->groupId()->equals(10)
    ->status()->equals(2)           // в работе
    ->deadline()->lessThan(Carbon::parse('2025-12-31'))
    ->createdDate()->greaterOrEqual(Carbon::parse('2025-01-01'))
    ->build();

foreach ($b24->getTaskScope()->task()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: $filter,
    select: ['id', 'title', 'status', 'deadline'],
) as $task) { ... }
```

### Доступные поля фильтра

**Целочисленные (`IntFieldConditionBuilder`):**
`id`, `parentId`, `groupId`, `stageId`, `forumTopicId`, `sprintId`,
`status`, `priority`, `mark`,
`createdBy`, `responsibleId`, `changedBy`, `closedBy`,
`timeEstimate`, `commentsCount`, `durationPlan`

**Строковые (`StringFieldConditionBuilder`):**
`title`, `description`, `xmlId`, `guid`

**Дата/время (`DateTimeFieldConditionBuilder`):**
`createdDate`, `changedDate`, `closedDate`, `deadline`,
`dateStart`, `startDatePlan`, `endDatePlan`

**Булевы (`BoolFieldConditionBuilder`):**
`multitask`, `taskControl`, `subordinate`, `favorite`, `isMuted`

**Пользовательские поля:**
```php
$filter = (new TaskFilter())
    ->userField('UF_CRM_TASK')->equals('some_value')
    ->build();
// UF_ префикс добавляется автоматически, если отсутствует
```

---

## 6. TaskItemResult — поля результата

Все поля camelCase. Даты — `CarbonImmutable|null`.

```php
$task = $b24->getTaskScope()->task()->get(123)->task();

// Идентификаторы
$task->id;              // int
$task->parentId;        // int|null — родительская задача
$task->groupId;         // int|null — ID рабочей группы/проекта
$task->stageId;         // int|null — ID стадии Kanban
$task->sprintId;        // int|null
$task->flowId;          // int|null

// Основные поля
$task->title;           // string
$task->description;     // string|null
$task->status;          // int|null (см. справочник статусов)
$task->priority;        // int|null (0=низкий, 1=средний, 2=высокий)
$task->mark;            // string|null ('P'=положительно, 'N'=отрицательно)

// Люди
$task->createdBy;       // int|null
$task->responsibleId;   // int|null
$task->changedBy;       // int|null
$task->closedBy;        // int|null
$task->accomplices;     // array|null — соисполнители [userId, ...]
$task->auditors;        // array|null — наблюдатели [userId, ...]

// Даты
$task->createdDate;     // CarbonImmutable|null
$task->changedDate;     // CarbonImmutable|null
$task->closedDate;      // CarbonImmutable|null
$task->deadline;        // CarbonImmutable|null
$task->dateStart;       // CarbonImmutable|null — фактическое начало
$task->startDatePlan;   // CarbonImmutable|null — плановое начало
$task->endDatePlan;     // CarbonImmutable|null — плановое завершение

// Флаги
$task->multitask;             // bool|null — для нескольких ответственных
$task->taskControl;           // bool|null — требует подтверждения
$task->allowChangeDeadline;   // bool|null
$task->allowTimeTracking;     // bool|null
$task->addInReport;           // bool|null — включить в отчёт
$task->subordinate;           // bool|null
$task->favorite;              // bool|null
$task->isMuted;               // bool|null

// Время
$task->timeEstimate;     // int|null — плановое время (секунды)
$task->timeSpentInLogs;  // int|null — фактически затрачено (секунды)
$task->durationPlan;     // int|null
$task->durationFact;     // int|null
$task->durationType;     // string|null ('seconds'|'minutes'|'hours'|'days')

// Счётчики
$task->commentsCount;         // int|null
$task->newCommentsCount;      // int|null

// Связи
$task->ufCrmTask;        // array|null — связанные CRM-элементы
$task->checklist;        // array|null
```

---

## 7. Пользовательские поля (UF_)

```php
// Получить через getUserfieldByFieldName (нормализует регистр автоматически)
$value = $task->getUserfieldByFieldName('UF_CRM_TASK');
// Или
$value = $task->getUserfieldByFieldName('UF_MY_FIELD');

// Прямой доступ через свойство (camelCase из UF_FIELD_NAME → ufFieldName)
$value = $task->ufCrmTask;
```

Управление пользовательскими полями задач:

```php
$scope = $b24->getTaskScope();

// Список всех UF-полей задач
$fields = $scope->userfield()->getList(['FIELD_NAME' => 'ASC'], []);

// Добавить поле
$scope->userfield()->add([
    'FIELD_NAME'     => 'UF_MY_FIELD',
    'USER_TYPE_ID'   => 'string',
    'EDIT_FORM_LABEL'=> ['ru' => 'Моё поле'],
]);
```

---

## 8. Чеклисты

```php
$checklist = $b24->getTaskScope()->checklistitem();

// Добавить пункт чеклиста
$itemId = $checklist->add(
    taskId:    123,
    title:     'Проверить результат',
    sort:      10,
    completed: false
)->getId();

// Получить один пункт
$item = $checklist->get(taskId: 123, itemId: $itemId);

// Список всех пунктов задачи
$items = $checklist->getList(taskId: 123, order: ['SORT_INDEX' => 'ASC']);

// Обновить пункт
$checklist->update(taskId: 123, itemId: $itemId, fields: [
    'TITLE'       => 'Новое название',
    'IS_COMPLETE' => 'Y',
]);

// Отметить как выполненный
$checklist->complete(taskId: 123, itemId: $itemId);

// Вернуть в активное состояние
$checklist->renew(taskId: 123, itemId: $itemId);

// Переместить после другого пункта
$checklist->moveAfterItem(taskId: 123, itemId: $itemId, afterItemId: $otherId);

// Удалить пункт
$checklist->delete(taskId: 123, itemId: $itemId);

// Проверить доступность действия
// actionId: 1=добавить время, 2=изменить, 3=удалить, 4=переключить
$checklist->isActionAllowed(taskId: 123, itemId: $itemId, actionId: 2);
```

---

## 9. Комментарии

```php
$comments = $b24->getTaskScope()->commentitem();

// Добавить комментарий
$itemId = $comments->add(taskId: 123, fields: [
    'POST_MESSAGE' => 'Текст комментария',
    'AUTHOR_ID'    => 5,
])->getId();

// Получить комментарий
$comment = $comments->get(taskId: 123, itemId: $itemId);

// Список комментариев
$list = $comments->getList(
    taskId: 123,
    order:  ['POST_DATE' => 'DESC'],
    filter: ['AUTHOR_ID' => 5]
);

// Обновить комментарий
$comments->update(taskId: 123, itemId: $itemId, fields: [
    'POST_MESSAGE' => 'Исправленный текст',
]);

// Удалить комментарий
$comments->delete(taskId: 123, itemId: $itemId);
```

---

## 10. Учёт времени

```php
$elapsed = $b24->getTaskScope()->elapseditem();

// Добавить запись о затраченном времени
$itemId = $elapsed->add(
    taskId:  123,
    seconds: 3600,           // 1 час
    text:    'Работа над задачей',
    userId:  5               // null = текущий пользователь
)->getId();

// Получить запись
$item = $elapsed->get(taskId: 123, itemId: $itemId);

// Список записей
$list = $elapsed->getList(
    taskId:   123,
    order:    ['CREATED_DATE' => 'DESC'],
    filter:   [],
    select:   [],
    page:     1,
    pageSize: 50
);

// Обновить запись
$elapsed->update(taskId: 123, itemId: $itemId, fields: [
    'SECONDS'      => 7200,
    'COMMENT_TEXT' => 'Обновлено',
]);

// Удалить запись
$elapsed->delete(taskId: 123, itemId: $itemId);

// Проверить доступность действия
// actionId: 1=добавить, 2=изменить, 3=удалить
$elapsed->isActionAllowed(taskId: 123, itemId: $itemId, actionId: 2);
```

---

## 11. Справочник статусов

### Статусы задачи (`status`)

| Код | Константа | Описание |
|---|---|---|
| 1 | `STATUS_NEW` | Новая (ждёт выполнения) |
| 2 | `STATUS_PENDING` | Ждёт контроля |
| 3 | `STATUS_IN_PROGRESS` | В работе |
| 4 | `STATUS_SUPPOSEDLY_COMPLETED` | Предположительно завершена |
| 5 | `STATUS_COMPLETED` | Завершена |
| 6 | `STATUS_DEFERRED` | Отложена |
| 7 | `STATUS_DECLINED` | Отклонена |

### Приоритет (`priority`)

| Код | Описание |
|---|---|
| 0 | Низкий |
| 1 | Средний (по умолчанию) |
| 2 | Высокий |

### Связанные CRM-элементы (`ufCrmTask`)

```php
// Создать задачу, связанную с CRM-элементами
$id = $b24->getTaskScope()->task()->add([
    'title'         => 'Задача по сделке',
    'responsibleId' => 5,
    'creatorId'     => 1,
    'ufCrmTask'     => ['D_123', 'C_45'],  // D=сделка, C=контакт, L=лид, CO=компания
])->task()->id;

// Получить связанные элементы
$task = $b24->getTaskScope()->task()->get(
    id: $id,
    select: ['id', 'title', 'ufCrmTask']
)->task();

foreach ($task->ufCrmTask ?? [] as $crmLink) {
    echo $crmLink . PHP_EOL;  // 'D_123', 'C_45'
}
```
