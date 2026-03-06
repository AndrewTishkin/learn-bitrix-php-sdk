# Bitrix24 PHP SDK v3 — CRM scope

> Общее (установка, авторизация, batch, фильтры) — см. [sdk-core.md](sdk-core.md)

## Оглавление

1. [Все сервисы CRM scope](#1-все-сервисы-crm-scope)
2. [Сделки (Deal)](#2-сделки-deal)
3. [Контакты (Contact)](#3-контакты-contact)
4. [Лиды (Lead)](#4-лиды-lead)
5. [Компании (Company)](#5-компании-company)
6. [Активности (Activity)](#6-активности-activity)
7. [Воронки и стадии сделок](#7-воронки-и-стадии-сделок)
8. [Связи сделки с контактами (DealContact)](#8-связи-сделки-с-контактами-dealcontact)
9. [Товарные позиции](#9-товарные-позиции)
10. [Статусы справочника (Status)](#10-статусы-справочника-status)
11. [Пользовательские поля (Userfield)](#11-пользовательские-поля-userfield)
12. [Дубли (Duplicate)](#12-дубли-duplicate)
13. [Timeline: комментарии и привязки](#13-timeline-комментарии-и-привязки)
14. [PHONE, EMAIL, WEB, IM в v3](#14-phone-email-web-im-в-v3)
15. [Массовые операции через batch](#15-массовые-операции-через-batch)

---

## 1. Все сервисы CRM scope

```php
$crm = $b24->getCRMScope();

// Основные сущности
$crm->deal()                    // Сделки
$crm->contact()                 // Контакты
$crm->lead()                    // Лиды
$crm->company()                 // Компании
$crm->quote()                   // Предложения
$crm->activity()                // Активности/дела
$crm->product()                 // Товары каталога

// Связи сущностей
$crm->dealContact()             // Контакты сделки
$crm->leadContact()             // Контакты лида
$crm->contactCompany()          // Компании контакта
$crm->companyContact()          // Контакты компании
$crm->quoteContact()            // Контакты предложения

// Воронки и стадии
$crm->dealCategory()            // Воронки сделок
$crm->dealCategoryStage()       // Стадии воронок

// Товарные позиции
$crm->dealProductRows()         // Товары в сделке
$crm->leadProductRows()         // Товары в лиде
$crm->quoteProductRows()        // Товары в предложении
$crm->itemProductrow()          // Товары crm.item (SPA)

// Пользовательские поля
$crm->dealUserfield()
$crm->contactUserfield()
$crm->companyUserfield()
$crm->leadUserfield()
$crm->quoteUserfield()
$crm->requisiteUserfield()
$crm->userfield()               // Типы UF-полей

// Реквизиты
$crm->requisite()
$crm->requisitePreset()
$crm->requisiteBankdetail()
$crm->requisiteLink()
$crm->requisitePresetField()

// Прочее
$crm->status()                  // Статусы справочника
$crm->statusEntity()            // Сущности справочника
$crm->timelineComment()         // Комментарии timeline
$crm->timelineBindings()        // Привязки timeline
$crm->item()                    // crm.item (SPA/smart process)
$crm->type()                    // Типы объектов CRM
$crm->address()                 // Адреса
$crm->currency()                // Валюты
$crm->localizations()           // Локализации валют
$crm->vat()                     // НДС
$crm->duplicate()               // Поиск дублей
$crm->enum()                    // Перечисления (типы активностей и т.д.)
$crm->settings()                // Настройки CRM
$crm->trigger()                 // Триггеры автоматизации
$crm->activityFetcher()         // Активности по типу
$crm->documentgeneratorNumerator() // Нумераторы документов
$crm->dealDetailsConfiguration()
$crm->contactDetailsConfiguration()
$crm->companyDetailsConfiguration()
$crm->leadDetailsConfiguration()
```

---

## 2. Сделки (Deal)

### CRUD

```php
$crm = $b24->getCRMScope();

// Получить
$deal = $crm->deal()->get(123)->deal();
echo $deal->ID . ' ' . $deal->TITLE . ' ' . $deal->STAGE_ID;

// Создать
$id = $crm->deal()->add([
    'TITLE'          => 'Новая сделка',
    'STAGE_ID'       => 'NEW',
    'CATEGORY_ID'    => 0,        // 0 = основная воронка
    'CURRENCY_ID'    => 'RUB',
    'OPPORTUNITY'    => '50000',
    'CONTACT_ID'     => 456,
    'COMPANY_ID'     => 789,
    'ASSIGNED_BY_ID' => 1,
])->getId();

// Обновить
$crm->deal()->update(123, ['STAGE_ID' => 'WON', 'OPPORTUNITY' => '75000']);

// Удалить
$crm->deal()->delete(123);

// Количество по фильтру
$count = $crm->deal()->countByFilter(['STAGE_ID' => 'NEW']);
```

### Batch (все сделки, автопагинация)

```php
foreach ($crm->deal()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: ['CATEGORY_ID' => 5, '>=DATE_MODIFY' => '2024-01-01'],
    select: ['ID', 'TITLE', 'STAGE_ID', 'CATEGORY_ID', 'DATE_MODIFY', 'ASSIGNED_BY_ID'],
    limit:  null
) as $deal) {
    echo $deal->ID . ': ' . $deal->TITLE . PHP_EOL;
}
```

### Поля DealItemResult

```php
$deal->ID                // int
$deal->TITLE             // string
$deal->STAGE_ID          // string ('NEW', 'WON', 'LOSE', 'C5:NEW', ...)
$deal->STAGE_SEMANTIC_ID // DealSemanticStage enum (S=выиграна, F=проиграна)
$deal->CATEGORY_ID       // int (0 = основная воронка)
$deal->ASSIGNED_BY_ID    // int
$deal->OPPORTUNITY       // string (сумма)
$deal->CURRENCY_ID       // Currency object
$deal->CONTACT_ID        // int|null
$deal->COMPANY_ID        // int|null
$deal->DATE_CREATE       // CarbonImmutable
$deal->DATE_MODIFY       // CarbonImmutable
$deal->CLOSED            // bool
$deal->SOURCE_ID         // string|null
$deal->COMMENTS          // string|null
```

---

## 3. Контакты (Contact)

### CRUD

```php
// Получить
$contact = $crm->contact()->get(456)->contact();

// Создать
$id = $crm->contact()->add([
    'NAME'           => 'Иван',
    'LAST_NAME'      => 'Петров',
    'ASSIGNED_BY_ID' => 1,
    'PHONE'          => [['VALUE' => '+79001234567', 'VALUE_TYPE' => 'WORK']],
    'EMAIL'          => [['VALUE' => 'ivan@example.com', 'VALUE_TYPE' => 'WORK']],
])->getId();

// Обновить (передавать ID телефона для замены, иначе добавится новый)
$crm->contact()->update(456, [
    'PHONE' => [['ID' => 999, 'VALUE' => '+79009876543', 'VALUE_TYPE' => 'WORK']],
]);

// Удалить
$crm->contact()->delete(456);

// Количество
$count = $crm->contact()->countByFilter(['ASSIGNED_BY_ID' => 5]);
```

### Batch

```php
foreach ($crm->contact()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: ['>=DATE_MODIFY' => '2024-06-01'],
    select: ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL', 'DATE_MODIFY'],
    limit:  200
) as $contact) {
    echo $contact->NAME . ' ' . $contact->LAST_NAME . PHP_EOL;
    // PHONE — объекты Phone[], см. раздел 14
    foreach ($contact->PHONE as $phone) {
        echo $phone->VALUE . PHP_EOL;
    }
}
```

### Поля ContactItemResult

```php
$contact->ID               // int
$contact->NAME             // string
$contact->LAST_NAME        // string|null
$contact->SECOND_NAME      // string|null
$contact->HONORIFIC        // string|null
$contact->BIRTHDATE        // CarbonImmutable|null
$contact->ASSIGNED_BY_ID   // int
$contact->COMPANY_ID       // int|null
$contact->DATE_CREATE      // CarbonImmutable
$contact->DATE_MODIFY      // CarbonImmutable
$contact->HAS_PHONE        // bool
$contact->HAS_EMAIL        // bool
$contact->PHONE            // Phone[]    ← объекты, не массив
$contact->EMAIL            // Email[]    ← объекты, не массив
$contact->WEB              // Website[]
$contact->IM               // InstantMessenger[]
```

---

## 4. Лиды (Lead)

### CRUD

```php
// Получить
$lead = $crm->lead()->get(789)->lead();
echo $lead->TITLE . ' — ' . $lead->STATUS_ID;

// Создать
$id = $crm->lead()->add([
    'TITLE'     => 'Лид с сайта',
    'NAME'      => 'Анна',
    'LAST_NAME' => 'Иванова',
    'STATUS_ID' => 'NEW',
    'SOURCE_ID' => 'WEB',
    'PHONE'     => [['VALUE' => '+79001112233', 'VALUE_TYPE' => 'WORK']],
])->getId();

// Обновить
$crm->lead()->update(789, ['STATUS_ID' => 'IN_PROCESS']);

// Удалить
$crm->lead()->delete(789);

// Количество
$count = $crm->lead()->countByFilter(['STATUS_ID' => 'NEW']);
```

### Batch

```php
foreach ($crm->lead()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: ['STATUS_ID' => 'NEW'],
    select: ['ID', 'TITLE', 'STATUS_ID', 'NAME', 'LAST_NAME', 'PHONE'],
) as $lead) {
    echo $lead->TITLE . PHP_EOL;
}
```

### Поля LeadItemResult

```php
$lead->ID                  // int
$lead->TITLE               // string
$lead->STATUS_ID           // string ('NEW', 'IN_PROCESS', 'CONVERTED', ...)
$lead->STATUS_SEMANTIC_ID  // string ('P'=в работе, 'S'=успешно, 'F'=провалено)
$lead->NAME                // string|null
$lead->LAST_NAME           // string|null
$lead->ASSIGNED_BY_ID      // int
$lead->DATE_CREATE         // CarbonImmutable
$lead->DATE_MODIFY         // CarbonImmutable
$lead->PHONE               // Phone[]
$lead->EMAIL               // Email[]
$lead->SOURCE_ID           // string|null
$lead->OPPORTUNITY         // string|null
$lead->CURRENCY_ID         // Currency object|null
```

---

## 5. Компании (Company)

```php
// Получить
$company = $crm->company()->get(101)->company();

// Создать
$id = $crm->company()->add([
    'TITLE'        => 'ООО Ромашка',
    'COMPANY_TYPE' => 'CUSTOMER',
    'INDUSTRY'     => 'IT',
    'PHONE'        => [['VALUE' => '+74951234567', 'VALUE_TYPE' => 'WORK']],
    'ASSIGNED_BY_ID' => 1,
])->getId();

// Обновить / Удалить
$crm->company()->update(101, ['INDUSTRY' => 'FINANCE']);
$crm->company()->delete(101);

// Batch
foreach ($crm->company()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: ['COMPANY_TYPE' => 'CUSTOMER'],
    select: ['ID', 'TITLE', 'COMPANY_TYPE', 'PHONE'],
) as $company) { ... }
```

---

## 6. Активности (Activity)

```php
// Создать
$id = $crm->activity()->add([
    'SUBJECT'        => 'Звонок клиенту',
    'TYPE_ID'        => 2,       // 1=email, 2=звонок, 3=задача, 4=встреча
    'DIRECTION'      => 2,       // 1=входящий, 2=исходящий
    'PRIORITY'       => 2,       // 1=низкий, 2=средний, 3=высокий
    'OWNER_ID'       => 123,     // ID сделки
    'OWNER_TYPE_ID'  => 2,       // 1=лид, 2=сделка, 3=контакт, 4=компания
    'RESPONSIBLE_ID' => 1,
    'START_TIME'     => '2024-07-01T10:00:00',
    'END_TIME'       => '2024-07-01T11:00:00',
    'COMPLETED'      => 'N',
    'DESCRIPTION'    => 'Обсудить КП',
])->getId();

// Обновить (завершить)
$crm->activity()->update($id, ['COMPLETED' => 'Y']);

// Удалить
$crm->activity()->delete($id);

// Список активностей сделки (через core->call, нет удобного typed-метода)
$result = $b24->core->call('crm.activity.list', [
    'filter' => ['OWNER_ID' => 123, 'OWNER_TYPE_ID' => 2],
    'select' => ['ID', 'SUBJECT', 'TYPE_ID', 'COMPLETED'],
]);
```

---

## 7. Воронки и стадии сделок

### Воронки (DealCategory)

```php
// Список всех воронок
$categories = $crm->dealCategory()->list()->dealCategories();
foreach ($categories as $cat) {
    echo $cat->ID . ': ' . $cat->NAME . ($cat->IS_DEFAULT ? ' (основная)' : '') . PHP_EOL;
}

// Создать / Обновить / Удалить
$crm->dealCategory()->add(['NAME' => 'Новая воронка', 'SORT' => 100]);
$crm->dealCategory()->update(5, ['NAME' => 'Переименованная']);
$crm->dealCategory()->delete(5);

// Узнать CATEGORY_ID через URL браузера: CRM → Сделки → categoryId=N в URL
// Или через прямой вызов:
$result = $b24->core->call('crm.category.list', ['entityTypeId' => 2]);
```

### Стадии воронки (DealCategoryStage)

```php
// Стадии воронки (CATEGORY_ID=0 = основная)
$stages = $crm->dealCategoryStage()->listForCategory(5)->dealCategoryStages();
foreach ($stages as $stage) {
    // STATUS_ID для воронки 0: 'NEW', 'WON', 'LOSE'
    // STATUS_ID для других воронок: 'C5:NEW', 'C5:1', 'C5:WON', ...
    echo $stage->STATUS_ID . ': ' . $stage->NAME . ' [' . $stage->SEMANTICS . ']' . PHP_EOL;
}
```

**Семантика:** `''` — в работе, `'S'` — выиграно, `'F'` — проиграно.

### Фильтр сделок по воронке

```php
// Сделки конкретной воронки
filter: ['CATEGORY_ID' => 5]
// Несколько воронок
filter: ['CATEGORY_ID' => [3, 5, 7]]
// Только активные (не проигранные)
filter: ['CATEGORY_ID' => 5, '!STAGE_SEMANTIC_ID' => 'F']
```

---

## 8. Связи сделки с контактами (DealContact)

```php
// Контакты сделки
$contacts = $crm->dealContact()->itemsGet(123)->dealContactItems();

// Добавить контакт
$crm->dealContact()->add(dealId: 123, contactId: 456, isPrimary: true, sort: 10);

// Заменить весь список
$crm->dealContact()->itemsSet(123, [
    ['CONTACT_ID' => 456, 'IS_PRIMARY' => 'Y', 'SORT' => 10],
    ['CONTACT_ID' => 789, 'IS_PRIMARY' => 'N', 'SORT' => 20],
]);

// Удалить один
$crm->dealContact()->delete(dealId: 123, contactId: 456);

// Очистить всех
$crm->dealContact()->itemsDelete(123);
```

---

## 9. Товарные позиции

```php
// Товары сделки
$rows = $crm->dealProductRows()->get(123)->productRows();
foreach ($rows as $row) {
    echo $row->PRODUCT_NAME . ': ' . $row->QUANTITY . ' x ' . $row->PRICE . PHP_EOL;
}

// Установить товары (заменяет весь список)
$crm->dealProductRows()->set(123, [
    [
        'PRODUCT_ID'   => 0,           // 0 = произвольный товар без каталога
        'PRODUCT_NAME' => 'Услуга X',
        'PRICE'        => '10000',
        'QUANTITY'     => 2,
        'CURRENCY_ID'  => 'RUB',
        'DISCOUNT_RATE' => 10,         // скидка %
    ],
]);

// Аналогично для лидов и предложений
$crm->leadProductRows()->get(789)->productRows();
$crm->leadProductRows()->set(789, [...]);
```

---

## 10. Статусы справочника (Status)

```php
// Все справочники (ENTITY_ID)
$entities = $crm->statusEntity()->list()->statusEntities();

// Статусы конкретного справочника
// ENTITY_ID: STATUS (лиды), DEAL_STAGE (сделки воронки 0), SOURCE, ...
foreach ($crm->status()->batch->list(
    order:  ['SORT' => 'ASC'],
    filter: ['ENTITY_ID' => 'STATUS'],   // статусы лидов
    select: ['*']
) as $status) {
    echo $status->STATUS_ID . ': ' . $status->NAME . PHP_EOL;
}

// Стадии сделок конкретной воронки: ENTITY_ID = 'DEAL_STAGE_' . $categoryId
// Основная воронка: 'DEAL_STAGE'
```

---

## 11. Пользовательские поля (Userfield)

```php
// Список UF-полей сделок
$fields = $crm->dealUserfield()->list([], [], ['*'])->getUserfields();

// Создать UF-поле
$crm->dealUserfield()->add([
    'FIELD_NAME'  => 'MY_FIELD',       // итоговое имя: UF_CRM_MY_FIELD
    'USER_TYPE_ID' => 'string',        // string, integer, double, date, boolean, enumeration
    'MULTIPLE'    => 'N',
    'MANDATORY'   => 'N',
    'EDIT_FORM_LABEL' => ['ru' => 'Моё поле'],
]);

// Чтение UF через magic __get
$deal = $crm->deal()->get(123)->deal();
$value = $deal->UF_CRM_MY_FIELD;

// Через метод (для ContactItemResult)
$value = $contact->getUserfieldByFieldName('UF_CRM_MY_FIELD');
// или без префикса:
$value = $contact->getUserfieldByFieldName('MY_FIELD');
```

---

## 12. Дубли (Duplicate)

```php
// По телефону
$dupes = $crm->duplicate()->findByPhone('+79001234567', entityTypeId: 3)->duplicates();
// entityTypeId: 1=лид, 2=сделка, 3=контакт, 4=компания

// По email
$dupes = $crm->duplicate()->findByEmail('test@example.com', entityTypeId: 3)->duplicates();
```

---

## 13. Timeline: комментарии и привязки

```php
// Добавить комментарий к сделке
$id = $crm->timelineComment()->add([
    'ENTITY_ID'      => 123,   // ID сделки
    'ENTITY_TYPE_ID' => 2,     // 1=лид, 2=сделка, 3=контакт, 4=компания
    'COMMENT'        => 'Клиент перезвонит завтра',
    'AUTHOR_ID'      => 1,
])->getId();

// Обновить / Удалить
$crm->timelineComment()->update($id, ['COMMENT' => 'Новый текст']);
$crm->timelineComment()->delete($id);

// Batch-список
foreach ($crm->timelineComment()->batch->list(
    order:  ['ID' => 'DESC'],
    filter: ['ENTITY_ID' => 123, 'ENTITY_TYPE_ID' => 2],
    select: ['*']
) as $comment) {
    echo $comment->COMMENT . PHP_EOL;
}
```

---

## 14. PHONE, EMAIL, WEB, IM в v3

В v3 эти поля на Result-объектах — **типизированные объекты**, не массивы.

### Чтение

```php
$contact = $crm->contact()->get(456)->contact();

foreach ($contact->PHONE as $phone) {
    echo $phone->VALUE;              // '+79001234567'
    echo $phone->VALUE_TYPE->value;  // 'WORK', 'HOME', 'MOBILE', ...
    echo $phone->ID;                 // int — внутренний ID записи
}

// Первый телефон безопасно
$first = !empty($contact->PHONE) ? $contact->PHONE[0]->VALUE : null;

// Email аналогично
foreach ($contact->EMAIL as $email) {
    echo $email->VALUE;
    echo $email->VALUE_TYPE->value;
}
```

### Запись (add/update) — всегда массив словарей

```php
$crm->contact()->add([
    'NAME'  => 'Иван',
    'PHONE' => [
        ['VALUE' => '+79001234567', 'VALUE_TYPE' => 'WORK'],
        ['VALUE' => '+79009876543', 'VALUE_TYPE' => 'MOBILE'],
    ],
    'EMAIL' => [
        ['VALUE' => 'ivan@example.com', 'VALUE_TYPE' => 'WORK'],
    ],
]);

// При обновлении телефона — передать ID, иначе добавится дополнительный
$crm->contact()->update(456, [
    'PHONE' => [['ID' => 999, 'VALUE' => '+79001112233', 'VALUE_TYPE' => 'WORK']],
]);
```

---

## 15. Массовые операции через batch

### Массовое создание

```php
$newDeals = [
    ['TITLE' => 'Сделка 1', 'STAGE_ID' => 'NEW', 'CATEGORY_ID' => 0],
    ['TITLE' => 'Сделка 2', 'STAGE_ID' => 'NEW', 'CATEGORY_ID' => 0],
    // ... сколько угодно, SDK разобьёт на пачки по 50
];

foreach ($crm->deal()->batch->add($newDeals) as $result) {
    echo 'Создана: ' . $result->getId() . PHP_EOL;
}

// Аналогично для контактов, лидов, компаний
$crm->contact()->batch->add([...]);
$crm->lead()->batch->add([...]);
```

### Массовое обновление

```php
// Структура: [entity_id => ['fields' => [...], 'params' => [...]]]
$updates = [
    123 => ['fields' => ['STAGE_ID' => 'WON']],
    456 => ['fields' => ['STAGE_ID' => 'LOSE', 'COMMENTS' => 'Отказ']],
];

foreach ($crm->deal()->batch->update($updates) as $result) {
    echo $result->isSuccess() ? 'OK' : 'Error' . PHP_EOL;
}
```

### Массовое удаление

```php
foreach ($crm->deal()->batch->delete([123, 456, 789]) as $result) {
    // DeletedItemBatchResult
}
```
