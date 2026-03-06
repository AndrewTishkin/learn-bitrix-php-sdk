# Bitrix24 PHP SDK v3 — справочник для работы с CRM

## Оглавление

1. [Требования и установка](#1-требования-и-установка)
2. [Авторизация: вебхук](#2-авторизация-вебхук)
3. [Авторизация: OAuth-приложение](#3-авторизация-oauth-приложение)
4. [Архитектура: как устроен SDK](#4-архитектура-как-устроен-sdk)
5. [CRM scope: все доступные сервисы](#5-crm-scope-все-доступные-сервисы)
6. [Сделки (Deal)](#6-сделки-deal)
7. [Контакты (Contact)](#7-контакты-contact)
8. [Лиды (Lead)](#8-лиды-lead)
9. [Компании (Company)](#9-компании-company)
10. [Активности (Activity)](#10-активности-activity)
11. [Воронки сделок (DealCategory / DealCategoryStage)](#11-воронки-сделок-dealcategory--dealcategorystage)
12. [Связи сделки с контактами (DealContact)](#12-связи-сделки-с-контактами-dealcontact)
13. [Товарные позиции (DealProductRows)](#13-товарные-позиции-dealproductrows)
14. [Статусы и стадии (Status)](#14-статусы-и-стадии-status)
15. [Пользовательские поля (Userfield)](#15-пользовательские-поля-userfield)
16. [Дубли (Duplicate)](#16-дубли-duplicate)
17. [Timeline: комментарии и привязки](#17-timeline-комментарии-и-привязки)
18. [Batch-операции: getTraversableList](#18-batch-операции-gettraversablelist)
19. [Batch-операции: массовое создание, обновление, удаление](#19-batch-операции-массовое-создание-обновление-удаление)
20. [Фильтры и операторы](#20-фильтры-и-операторы)
21. [Работа с результатами: типизированные DTO](#21-работа-с-результатами-типизированные-dto)
22. [Работа с PHONE, EMAIL, WEB, IM (v3)](#22-работа-с-phone-email-web-im-v3)
23. [Прямые вызовы через core->call()](#23-прямые-вызовы-через-core-call)
24. [Типичные ошибки и их причины](#24-типичные-ошибки-и-их-причины)
25. [Получение сырых данных от API](#25-получение-сырых-данных-от-api)

---

## 1. Требования и установка

**Требования:**
- PHP >= 8.4
- Расширения: `ext-curl`, `ext-intl`

**composer.json:**
```json
{
    "require": {
        "php": ">=8.4",
        "bitrix24/b24phpsdk": "^3.0"
    },
    "config": {
        "allow-plugins": { "php-http/discovery": true }
    }
}
```

```bash
composer install
```

**Автозагрузка:**
```php
require_once __DIR__ . '/vendor/autoload.php';
```

---

## 2. Авторизация: вебхук

Вебхук — простейший способ. Не требует приложения, работает с постоянным токеном.

**Ограничение:** вебхук не поддерживает офлайн-события (для них нужен OAuth).

```php
use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Логгер (можно NullLogger для продакшена)
$logger = new Logger('b24');
$logger->pushHandler(new StreamHandler('php://stdout'));

$factory = new ServiceBuilderFactory(new EventDispatcher(), $logger);

$b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook(
    'https://your-portal.bitrix24.com/rest/1/your-token/',
    new EventDispatcher(),
    $logger
);

// Проверка соединения
$user = $b24->getMainScope()->main()->getCurrentUserProfile()->user();
echo $user->NAME . ' ' . $user->LAST_NAME;
```

**Webhook URL из переменной окружения (рекомендуется):**
```php
$webhookUrl = getenv('B24_WEBHOOK_URL');
// или
$webhookUrl = $_ENV['B24_WEBHOOK_URL'];

$b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook(
    $webhookUrl,
    new EventDispatcher(),
    $logger
);
```

---

## 3. Авторизация: OAuth-приложение

Нужно для офлайн-событий, тиражных приложений, работы от имени нескольких порталов.

**Инициализация с сохранёнными токенами:**
```php
use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\ApplicationProfile;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Psr\Log\NullLogger;

$appProfile = ApplicationProfile::initFromArray([
    'BITRIX24_PHP_SDK_APPLICATION_CLIENT_ID'     => 'your_client_id',
    'BITRIX24_PHP_SDK_APPLICATION_CLIENT_SECRET' => 'your_client_secret',
    'BITRIX24_PHP_SDK_APPLICATION_SCOPE'         => 'crm,task',
]);

$factory = new ServiceBuilderFactory(new EventDispatcher(), new NullLogger());

// Загружаем токены из хранилища
$tokens = json_decode(file_get_contents('tokens.json'), true);

$b24 = $factory->init(
    $appProfile,
    new AuthToken(
        $tokens['access_token'],
        $tokens['refresh_token'],
        0  // 0 = считать истёкшим, SDK обновит через refresh_token
    ),
    $tokens['client_endpoint'],
    'https://oauth.bitrix.info/'
);
// SDK автоматически обновляет access_token при истечении
```

**install.php — получение первых токенов (вызывается Битрикс24 при создании приложения):**
```php
$auth = $_POST['auth'];

file_put_contents('tokens.json', json_encode([
    'access_token'    => $auth['access_token'],
    'refresh_token'   => $auth['refresh_token'],
    'client_endpoint' => $auth['client_endpoint'],
    'domain'          => $auth['domain'] ?? '',
]));
```

---

## 4. Архитектура: как устроен SDK

```
ServiceBuilderFactory::createServiceBuilderFromWebhook(url)
  └─ ServiceBuilder ($b24)
       ├─ getCRMScope()       → CRMServiceBuilder
       │    ├─ deal()         → Deal service
       │    ├─ contact()      → Contact service
       │    ├─ lead()         → Lead service
       │    ├─ company()      → Company service
       │    └─ ... (полный список в разделе 5)
       │
       ├─ getTaskScope()      → TaskServiceBuilder
       ├─ getMainScope()      → MainServiceBuilder
       └─ core                → CoreInterface (прямые вызовы API)
```

**Каждый сервис содержит:**
- Методы одиночных запросов: `get()`, `add()`, `update()`, `delete()`, `list()`, `fields()`
- `->batch` — сервис для batch-операций с автопагинацией

---

## 5. CRM scope: все доступные сервисы

```php
$crm = $b24->getCRMScope();

// Основные сущности
$crm->deal()                    // Сделки
$crm->contact()                 // Контакты
$crm->lead()                    // Лиды
$crm->company()                 // Компании
$crm->quote()                   // Предложения
$crm->activity()                // Дела/активности
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
$crm->itemProductrow()          // Товарные позиции для crm.item

// Пользовательские поля
$crm->dealUserfield()           // Пользовательские поля сделок
$crm->contactUserfield()        // Пользовательские поля контактов
$crm->companyUserfield()        // Пользовательские поля компаний
$crm->leadUserfield()           // Пользовательские поля лидов
$crm->quoteUserfield()          // Пользовательские поля предложений
$crm->requisiteUserfield()      // Пользовательские поля реквизитов
$crm->userfield()               // Типы пользовательских полей

// Реквизиты
$crm->requisite()               // Реквизиты
$crm->requisitePreset()         // Шаблоны реквизитов
$crm->requisiteBankdetail()     // Банковские реквизиты
$crm->requisiteLink()           // Привязки реквизитов
$crm->requisitePresetField()    // Поля шаблонов реквизитов

// Статусы справочника
$crm->status()                  // Статусы (стадии лидов и т.д.)
$crm->statusEntity()            // Сущности справочника статусов

// Timeline
$crm->timelineComment()         // Комментарии в timeline
$crm->timelineBindings()        // Привязки в timeline

// Конфигурация карточек
$crm->dealDetailsConfiguration()
$crm->contactDetailsConfiguration()
$crm->companyDetailsConfiguration()
$crm->leadDetailsConfiguration()
$crm->itemDetailsConfiguration()

// Прочее
$crm->item()                    // crm.item (универсальный, для SPA)
$crm->itemProductrow()          // Товары crm.item
$crm->type()                    // Типы объектов CRM (SPA)
$crm->address()                 // Адреса
$crm->currency()                // Валюты
$crm->localizations()           // Локализации валют
$crm->vat()                     // НДС
$crm->duplicate()               // Поиск дублей
$crm->enum()                    // Перечисления (статусы активностей и т.д.)
$crm->settings()                // Настройки CRM
$crm->trigger()                 // Автоматизация: триггеры
$crm->activityFetcher()         // Получение активностей по типу
$crm->documentgeneratorNumerator() // Нумераторы документов
```

---

## 6. Сделки (Deal)

### Одиночные запросы

```php
$crm = $b24->getCRMScope();

// Получить по ID
$deal = $crm->deal()->get(123)->deal();
echo $deal->ID . ' ' . $deal->TITLE;
echo $deal->STAGE_ID;         // стадия
echo $deal->CATEGORY_ID;      // ID воронки
echo $deal->ASSIGNED_BY_ID;   // ответственный

// Создать
$result = $crm->deal()->add([
    'TITLE'       => 'Новая сделка',
    'STAGE_ID'    => 'NEW',
    'CATEGORY_ID' => 0,        // 0 = основная воронка
    'CURRENCY_ID' => 'RUB',
    'OPPORTUNITY' => '50000',
    'CONTACT_ID'  => 456,
    'COMPANY_ID'  => 789,
    'ASSIGNED_BY_ID' => 1,
]);
$newDealId = $result->getId();

// Обновить
$crm->deal()->update(123, [
    'STAGE_ID'    => 'WON',
    'OPPORTUNITY' => '75000',
]);

// Удалить
$crm->deal()->delete(123);

// Поля сущности (схема)
$fields = $crm->deal()->fields()->getFieldsDescription();

// Количество по фильтру
$count = $crm->deal()->countByFilter(['STAGE_ID' => 'NEW']);

// Одна страница (без автопагинации)
$deals = $crm->deal()->list(
    order:  ['DATE_MODIFY' => 'DESC'],
    filter: ['CATEGORY_ID' => 5],
    select: ['ID', 'TITLE', 'STAGE_ID'],
    startItem: 0
)->getDeals();
```

### Batch — все сделки с автопагинацией

```php
// Перебрать все сделки определённой воронки
foreach ($crm->deal()->batch->list(
    order:  ['ID' => 'ASC'],              // сортировка по ID — быстрый режим
    filter: ['CATEGORY_ID' => 5],
    select: ['ID', 'TITLE', 'STAGE_ID', 'CATEGORY_ID', 'DATE_MODIFY', 'ASSIGNED_BY_ID'],
    limit:  null                           // null = без ограничений
) as $deal) {
    // $deal — DealItemResult
    echo $deal->ID . ': ' . $deal->TITLE . PHP_EOL;
}

// С лимитом
foreach ($crm->deal()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: ['>=DATE_MODIFY' => '2024-01-01'],
    select: ['ID', 'TITLE'],
    limit:  200
) as $deal) {
    // первые 200 сделок
}
```

### Поля DealItemResult

```php
$deal->ID              // int
$deal->TITLE           // string
$deal->STAGE_ID        // string ('NEW', 'WON', 'LOSE', 'C1:NEW', ...)
$deal->CATEGORY_ID     // int (0 = основная воронка)
$deal->ASSIGNED_BY_ID  // int
$deal->OPPORTUNITY     // string (сумма)
$deal->CURRENCY_ID     // string ('RUB', 'USD', ...)
$deal->CONTACT_ID      // int
$deal->COMPANY_ID      // int
$deal->DATE_CREATE     // CarbonImmutable
$deal->DATE_MODIFY     // CarbonImmutable
$deal->CLOSED          // bool
$deal->SOURCE_ID       // string|null
```

---

## 7. Контакты (Contact)

### Одиночные запросы

```php
$crm = $b24->getCRMScope();

// Получить по ID
$contact = $crm->contact()->get(456)->contact();
echo $contact->NAME . ' ' . $contact->LAST_NAME;

// Создать
$result = $crm->contact()->add([
    'NAME'        => 'Иван',
    'LAST_NAME'   => 'Петров',
    'ASSIGNED_BY_ID' => 1,
    'PHONE'       => [['VALUE' => '+79001234567', 'VALUE_TYPE' => 'WORK']],
    'EMAIL'       => [['VALUE' => 'ivan@example.com', 'VALUE_TYPE' => 'WORK']],
]);
$newContactId = $result->getId();

// Обновить
$crm->contact()->update(456, [
    'LAST_NAME' => 'Сидоров',
    'PHONE'     => [['VALUE' => '+79009876543', 'VALUE_TYPE' => 'WORK']],
]);

// Удалить
$crm->contact()->delete(456);

// Количество
$count = $crm->contact()->countByFilter(['ASSIGNED_BY_ID' => 5]);
```

### Batch — все контакты с автопагинацией

```php
foreach ($crm->contact()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: ['>=DATE_MODIFY' => '2024-06-01'],
    select: ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL', 'DATE_MODIFY'],
    limit:  200
) as $contact) {
    // $contact — ContactItemResult
    echo $contact->NAME . ' ' . $contact->LAST_NAME . PHP_EOL;

    // Доступ к телефонам (ВАЖНО: в v3 это объекты Phone[], не массив)
    foreach ($contact->PHONE as $phone) {
        echo $phone->VALUE . ' (' . $phone->VALUE_TYPE->value . ')' . PHP_EOL;
    }
}
```

### Поля ContactItemResult (ключевые)

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

// Типизированные объекты (не массивы!)
$contact->PHONE            // Phone[]
$contact->EMAIL            // Email[]
$contact->WEB              // Website[]
$contact->IM               // InstantMessenger[]
```

---

## 8. Лиды (Lead)

### Одиночные запросы

```php
$crm = $b24->getCRMScope();

// Получить
$lead = $crm->lead()->get(789)->lead();
echo $lead->TITLE . ' — ' . $lead->STATUS_ID;

// Создать
$result = $crm->lead()->add([
    'TITLE'       => 'Лид с сайта',
    'NAME'        => 'Анна',
    'LAST_NAME'   => 'Иванова',
    'STATUS_ID'   => 'NEW',
    'SOURCE_ID'   => 'WEB',
    'PHONE'       => [['VALUE' => '+79001112233', 'VALUE_TYPE' => 'WORK']],
    'EMAIL'       => [['VALUE' => 'anna@example.com', 'VALUE_TYPE' => 'WORK']],
]);

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
    select: ['ID', 'TITLE', 'STATUS_ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL'],
) as $lead) {
    echo $lead->TITLE . PHP_EOL;
}
```

### Поля LeadItemResult

```php
$lead->ID                 // int
$lead->TITLE              // string
$lead->STATUS_ID          // string ('NEW', 'IN_PROCESS', 'CONVERTED', ...)
$lead->STATUS_SEMANTIC_ID // string ('P' — в работе, 'S' — успешно, 'F' — провалено)
$lead->NAME               // string|null
$lead->LAST_NAME          // string|null
$lead->ASSIGNED_BY_ID     // int
$lead->COMPANY_ID         // int|null
$lead->DATE_CREATE        // CarbonImmutable
$lead->DATE_MODIFY        // CarbonImmutable
$lead->PHONE              // Phone[]
$lead->EMAIL              // Email[]
$lead->SOURCE_ID          // string|null
$lead->OPPORTUNITY        // string|null
$lead->CURRENCY_ID        // string|null
```

---

## 9. Компании (Company)

### Одиночные запросы

```php
$crm = $b24->getCRMScope();

// Получить
$company = $crm->company()->get(101)->company();
echo $company->TITLE;

// Создать
$result = $crm->company()->add([
    'TITLE'       => 'ООО Ромашка',
    'COMPANY_TYPE' => 'CUSTOMER',
    'INDUSTRY'    => 'IT',
    'PHONE'       => [['VALUE' => '+74951234567', 'VALUE_TYPE' => 'WORK']],
    'EMAIL'       => [['VALUE' => 'info@romashka.ru', 'VALUE_TYPE' => 'WORK']],
    'ASSIGNED_BY_ID' => 1,
]);

// Обновить
$crm->company()->update(101, ['INDUSTRY' => 'FINANCE']);

// Удалить
$crm->company()->delete(101);

// Количество
$count = $crm->company()->countByFilter(['COMPANY_TYPE' => 'CUSTOMER']);
```

### Batch

```php
foreach ($crm->company()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: ['COMPANY_TYPE' => 'CUSTOMER'],
    select: ['ID', 'TITLE', 'COMPANY_TYPE', 'PHONE', 'EMAIL'],
) as $company) {
    echo $company->TITLE . PHP_EOL;
}
```

---

## 10. Активности (Activity)

```php
$crm = $b24->getCRMScope();

// Создать активность (дело/задачу)
$result = $crm->activity()->add([
    'SUBJECT'     => 'Звонок клиенту',
    'TYPE_ID'     => 2,              // 1-email, 2-звонок, 3-задача, 4-встреча
    'DIRECTION'   => 2,              // 1-входящий, 2-исходящий
    'PRIORITY'    => 2,              // 1-низкий, 2-средний, 3-высокий
    'OWNER_ID'    => 123,            // ID сделки
    'OWNER_TYPE_ID' => 2,            // 1-лид, 2-сделка, 3-контакт, 4-компания
    'RESPONSIBLE_ID' => 1,
    'START_TIME'  => '2024-07-01T10:00:00',
    'END_TIME'    => '2024-07-01T11:00:00',
    'COMPLETED'   => 'N',
    'DESCRIPTION' => 'Обсудить коммерческое предложение',
]);

// Получить
$activity = $crm->activity()->get($result->getId())->activity();

// Обновить
$crm->activity()->update($result->getId(), ['COMPLETED' => 'Y']);

// Удалить
$crm->activity()->delete($result->getId());

// Список активностей сделки (через прямой вызов)
$activities = $b24->core->call('crm.activity.list', [
    'filter' => ['OWNER_ID' => 123, 'OWNER_TYPE_ID' => 2],
    'select' => ['ID', 'SUBJECT', 'TYPE_ID', 'START_TIME', 'COMPLETED'],
]);
```

---

## 11. Воронки сделок (DealCategory / DealCategoryStage)

### Воронки

```php
$crm = $b24->getCRMScope();

// Список всех воронок
$categories = $crm->dealCategory()->list()->dealCategories();
foreach ($categories as $cat) {
    echo $cat->ID . ': ' . $cat->NAME . ($cat->IS_DEFAULT ? ' (основная)' : '') . PHP_EOL;
}

// Получить по ID
$cat = $crm->dealCategory()->get(5)->dealCategory();

// Создать воронку
$result = $crm->dealCategory()->add([
    'NAME'       => 'Новая воронка',
    'SORT'       => 100,
    'IS_DEFAULT' => 'N',
]);

// Обновить
$crm->dealCategory()->update(5, ['NAME' => 'Переименованная воронка']);

// Удалить
$crm->dealCategory()->delete(5);
```

### Стадии воронки

```php
// Стадии основной воронки (CATEGORY_ID = 0)
$stages = $crm->dealCategoryStage()->listForCategory(0)->dealCategoryStages();
foreach ($stages as $stage) {
    echo $stage->STATUS_ID . ': ' . $stage->NAME . ' (' . $stage->SEMANTICS . ')' . PHP_EOL;
}

// Стадии конкретной воронки
$stages = $crm->dealCategoryStage()->listForCategory(5)->dealCategoryStages();
```

**Семантика стадий:**
- `''` или `null` — в работе
- `'S'` — успешное завершение (выиграно)
- `'F'` — неуспешное завершение (проиграно)

---

## 12. Связи сделки с контактами (DealContact)

```php
$crm = $b24->getCRMScope();

// Получить контакты сделки
$contacts = $crm->dealContact()->itemsGet(123)->dealContactItems();
foreach ($contacts as $dc) {
    echo 'Contact ID: ' . $dc->CONTACT_ID . ', primary: ' . ($dc->IS_PRIMARY ? 'Y' : 'N') . PHP_EOL;
}

// Добавить контакт к сделке
$crm->dealContact()->add(
    dealId:    123,
    contactId: 456,
    isPrimary: true,
    sort:      10
);

// Заменить весь список контактов сделки
$crm->dealContact()->itemsSet(123, [
    ['CONTACT_ID' => 456, 'IS_PRIMARY' => 'Y', 'SORT' => 10],
    ['CONTACT_ID' => 789, 'IS_PRIMARY' => 'N', 'SORT' => 20],
]);

// Удалить один контакт из сделки
$crm->dealContact()->delete(dealId: 123, contactId: 456);

// Очистить все контакты сделки
$crm->dealContact()->itemsDelete(123);
```

---

## 13. Товарные позиции (DealProductRows)

```php
$crm = $b24->getCRMScope();

// Получить товары сделки
$rows = $crm->dealProductRows()->get(123)->productRows();
foreach ($rows as $row) {
    echo $row->PRODUCT_NAME . ': ' . $row->QUANTITY . ' x ' . $row->PRICE . PHP_EOL;
}

// Установить товары (заменяет весь список)
$crm->dealProductRows()->set(123, [
    [
        'PRODUCT_ID'   => 0,            // 0 = произвольный товар без привязки к каталогу
        'PRODUCT_NAME' => 'Услуга X',
        'PRICE'        => '10000',
        'QUANTITY'     => 2,
        'CURRENCY_ID'  => 'RUB',
        'DISCOUNT_RATE'  => 10,         // скидка %
    ],
]);
```

---

## 14. Статусы и стадии (Status)

```php
$crm = $b24->getCRMScope();

// Список всех справочников статусов
$entities = $crm->statusEntity()->list()->statusEntities();
foreach ($entities as $e) {
    echo $e->ENTITY_ID . ': ' . $e->NAME . PHP_EOL;
}

// Статусы конкретного справочника
// ENTITY_ID: STATUS — стадии лида, DEAL_STAGE — стадии воронки 0, SOURCE — источники, и т.д.
$statuses = $crm->status()->list(
    order:  ['SORT' => 'ASC'],
    filter: ['ENTITY_ID' => 'STATUS'],    // статусы лидов
    select: ['*']
)->statuses();

foreach ($statuses as $status) {
    echo $status->STATUS_ID . ': ' . $status->NAME . PHP_EOL;
}

// Batch-вариант
foreach ($crm->status()->batch->list(
    order:  ['SORT' => 'ASC'],
    filter: ['ENTITY_ID' => 'STATUS'],
    select: ['*']
) as $status) {
    echo $status->STATUS_ID . ': ' . $status->NAME . PHP_EOL;
}
```

---

## 15. Пользовательские поля (Userfield)

```php
$crm = $b24->getCRMScope();

// Список UF-полей сделок
$fields = $crm->dealUserfield()->list([], [], ['*'])->getUserfields();
foreach ($fields as $uf) {
    echo $uf->FIELD_NAME . ': ' . $uf->USER_TYPE_ID . PHP_EOL;
}

// Создать UF-поле для сделок
$crm->dealUserfield()->add([
    'FIELD_NAME'  => 'MY_FIELD',          // итоговое имя будет UF_CRM_MY_FIELD
    'USER_TYPE_ID' => 'string',           // string, integer, double, date, boolean, enumeration, ...
    'MULTIPLE'    => 'N',
    'MANDATORY'   => 'N',
    'EDIT_FORM_LABEL' => ['ru' => 'Моё поле', 'en' => 'My Field'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Моё поле', 'en' => 'My Field'],
]);

// Чтение UF-поля из результата (через магический __get)
$deal = $crm->deal()->get(123)->deal();
$customValue = $deal->UF_CRM_MY_FIELD;

// Через getUserfieldByFieldName (для ContactItemResult)
$contact = $crm->contact()->get(456)->contact();
$customValue = $contact->getUserfieldByFieldName('UF_CRM_MY_FIELD');
```

---

## 16. Дубли (Duplicate)

```php
$crm = $b24->getCRMScope();

// Поиск дублей по телефону
$duplicates = $crm->duplicate()->findByPhone(
    phone: '+79001234567',
    entityTypeId: 3   // 1=лид, 2=сделка, 3=контакт, 4=компания
)->duplicates();

// Поиск дублей по email
$duplicates = $crm->duplicate()->findByEmail(
    email: 'test@example.com',
    entityTypeId: 3
)->duplicates();
```

---

## 17. Timeline: комментарии и привязки

```php
$crm = $b24->getCRMScope();

// Добавить комментарий в timeline сделки
$result = $crm->timelineComment()->add([
    'ENTITY_ID'      => 123,    // ID сделки
    'ENTITY_TYPE_ID' => 2,      // 1=лид, 2=сделка, 3=контакт, 4=компания
    'COMMENT'        => 'Клиент перезвонит завтра',
    'AUTHOR_ID'      => 1,
]);

// Обновить комментарий
$crm->timelineComment()->update($result->getId(), [
    'COMMENT' => 'Обновлённый комментарий',
]);

// Удалить комментарий
$crm->timelineComment()->delete($result->getId());

// Batch-список комментариев
foreach ($crm->timelineComment()->batch->list(
    order:  ['ID' => 'DESC'],
    filter: ['ENTITY_ID' => 123, 'ENTITY_TYPE_ID' => 2],
    select: ['*']
) as $comment) {
    echo $comment->COMMENT . PHP_EOL;
}
```

---

## 18. Batch-операции: getTraversableList

`getTraversableList` — движок авто-пагинации SDK. Вызывается неявно через `batch->list()`.

### Как работает автоматически

```php
// Этот вызов автоматически выбирает стратегию пагинации:
foreach ($crm->deal()->batch->list(
    order:  ['ID' => 'ASC'],   // ← сортировка по ID = БЫСТРЫЙ режим
    filter: ['CATEGORY_ID' => 5],
    select: ['ID', 'TITLE', 'STAGE_ID'],
) as $deal) {
    // SDK делает batch-запросы по 50 страниц за раз
    // Каждый batch содержит до 50 подзапросов × 50 элементов = 2500 за один HTTP-запрос
}
```

### Два режима пагинации

**Быстрый (sort by ID)** — используется когда `order` пустой или первое поле = `ID`:
```php
// Вместо start=0, start=50, start=100... использует динамические ссылки:
// cmd_1: filter[>ID]=$result[cmd_0][49][ID]&start=-1
// cmd_2: filter[>ID]=$result[cmd_1][49][ID]&start=-1
// Всё в одном HTTP-запросе к batch-методу
$crm->deal()->batch->list(order: [], filter: [...], select: [...]);
$crm->deal()->batch->list(order: ['ID' => 'ASC'], filter: [...], select: [...]);
```

**Медленный (sort by другому полю)** — используется при `order: ['DATE_MODIFY' => 'DESC']` и т.д.:
```php
// SDK логирует предупреждение 'getTraversableList.unoptimalParams'
// Использует start=0, 50, 100... — медленнее, но работает корректно
$crm->deal()->batch->list(order: ['DATE_MODIFY' => 'DESC'], filter: [...], select: [...]);
```

### Прямой вызов getTraversableList (через core->batch)

Если нужен метод, которого нет в typed-сервисах:
```php
// getTraversableList доступен через $b24->batch
foreach ($b24->batch->getTraversableList(
    'crm.deal.list',
    ['ID' => 'ASC'],
    ['CATEGORY_ID' => 5],
    ['ID', 'TITLE'],
    200    // лимит
) as $rawDeal) {
    // $rawDeal — сырой массив ['ID' => '123', 'TITLE' => '...']
}
```

---

## 19. Batch-операции: массовое создание, обновление, удаление

### Массовое создание

```php
// Создать сразу много сделок (SDK сам разобьёт на пачки по 50)
$newDeals = [
    ['TITLE' => 'Сделка 1', 'STAGE_ID' => 'NEW', 'CATEGORY_ID' => 0],
    ['TITLE' => 'Сделка 2', 'STAGE_ID' => 'NEW', 'CATEGORY_ID' => 0],
    // ... до тысяч элементов
];

foreach ($crm->deal()->batch->add($newDeals) as $result) {
    // $result — AddedItemBatchResult
    echo 'Создана сделка ID: ' . $result->getId() . PHP_EOL;
}
```

### Массовое обновление

```php
// Обновить несколько сделок
// Структура: [deal_id => ['fields' => [...], 'params' => [...]]]
$updates = [
    123 => ['fields' => ['STAGE_ID' => 'WON']],
    456 => ['fields' => ['STAGE_ID' => 'LOSE', 'COMMENTS' => 'Отказ']],
    789 => ['fields' => ['OPPORTUNITY' => '100000', 'CURRENCY_ID' => 'RUB']],
];

foreach ($crm->deal()->batch->update($updates) as $result) {
    // $result — UpdatedItemBatchResult
    echo $result->isSuccess() ? 'OK' : 'Error' . PHP_EOL;
}
```

### Массовое удаление

```php
// Удалить список сделок по ID
$idsToDelete = [123, 456, 789, 1000, 1001];

foreach ($crm->deal()->batch->delete($idsToDelete) as $result) {
    // $result — DeletedItemBatchResult
}
```

### Аналогично для других сущностей

```php
// Контакты
$crm->contact()->batch->add([...]);
$crm->contact()->batch->update([...]);
$crm->contact()->batch->delete([...]);

// Лиды
$crm->lead()->batch->add([...]);
$crm->lead()->batch->update([...]);
$crm->lead()->batch->delete([...]);

// Компании
$crm->company()->batch->add([...]);
$crm->company()->batch->update([...]);
$crm->company()->batch->delete([...]);
```

---

## 20. Фильтры и операторы

Фильтры передаются в `filter` в методы `list()` и `batch->list()`.

### Синтаксис операторов

Оператор — **префикс к имени поля** в ключе массива:

```php
$filter = [
    'ID'              => 123,          // точное совпадение (=)
    '!ID'             => 123,          // не равно (!=)
    '>ID'             => 100,          // больше (>)
    '>=ID'            => 100,          // больше или равно (>=)
    '<ID'             => 500,          // меньше (<)
    '<=ID'            => 500,          // меньше или равно (<=)
    '%TITLE'          => 'ромашка',    // содержит (LIKE %...%)
    'TITLE'           => ['А', 'Б'],   // одно из значений (IN)
    '!STAGE_ID'       => 'LOSE',       // исключить значение
];
```

### Фильтр по дате

```php
$filter = [
    '>=DATE_MODIFY' => '2024-01-01',
    '<DATE_MODIFY'  => '2024-07-01',
    '>=DATE_CREATE' => (new DateTime('-30 days'))->format('Y-m-d H:i:s'),
];
```

### Фильтр по воронке и стадии

```php
$filter = [
    'CATEGORY_ID' => 5,                // одна воронка
    'CATEGORY_ID' => [3, 5, 7],        // несколько воронок
    'STAGE_ID'    => 'C5:NEW',         // стадия конкретной воронки
    '!STAGE_SEMANTIC_ID' => 'F',       // исключить проигранные
];
```

### Фильтр по ответственному

```php
$filter = [
    'ASSIGNED_BY_ID' => 42,            // один ответственный
    'ASSIGNED_BY_ID' => [42, 43, 44],  // несколько
];
```

### Фильтр по связанной сущности

```php
// Сделки конкретного контакта
$filter = ['CONTACT_ID' => 456];

// Сделки конкретной компании
$filter = ['COMPANY_ID' => 101];

// Контакты компании
$filter = ['COMPANY_ID' => 101];
```

### Сортировка

```php
$order = ['DATE_MODIFY' => 'DESC'];   // по дате изменения, убывание
$order = ['ID' => 'ASC'];             // по ID, возрастание (быстрый режим batch)
$order = ['TITLE' => 'ASC'];          // по заголовку (медленный режим batch)
```

### Select (выбор полей)

```php
// Явно перечислить нужные поля (рекомендуется для производительности)
$select = ['ID', 'TITLE', 'STAGE_ID', 'CATEGORY_ID', 'DATE_MODIFY'];

// Все поля (медленно, использовать только для отладки)
$select = ['*'];

// UF-поля включаются через *
$select = ['*', 'UF_*'];             // все поля включая пользовательские
$select = ['ID', 'TITLE', 'UF_CRM_MY_FIELD'];  // конкретное UF-поле
```

---

## 21. Работа с результатами: типизированные DTO

### Одиночный результат

```php
// get() возвращает Result-объект, из него извлекаем DTO
$dealResult = $crm->deal()->get(123);
$deal = $dealResult->deal();         // DealItemResult

// add() возвращает ID созданного элемента
$addResult = $crm->deal()->add([...]);
$newId = $addResult->getId();        // int

// update() / delete() возвращают булево
$updateResult = $crm->deal()->update(123, [...]);
$ok = $updateResult->isSuccess();
```

### Список (одна страница без пагинации)

```php
$dealsResult = $crm->deal()->list([], [], ['ID', 'TITLE'], 0);
$deals = $dealsResult->getDeals();   // DealItemResult[]
$total = $dealsResult->getCoreResponse()->getResponseData()->getPagination()->getTotal();
$nextItem = $dealsResult->getCoreResponse()->getResponseData()->getPagination()->getNextItem();
```

### Batch-результат (Generator)

```php
foreach ($crm->deal()->batch->list([], [], ['ID', 'TITLE']) as $deal) {
    // $deal — DealItemResult, данные уже типизированы
    $id    = $deal->ID;       // int
    $title = $deal->TITLE;    // string
    $date  = $deal->DATE_MODIFY; // CarbonImmutable
    echo $date->format('d.m.Y') . PHP_EOL;
}
```

### Работа с датами (CarbonImmutable)

```php
$deal = $crm->deal()->get(123)->deal();
$date = $deal->DATE_MODIFY;             // CarbonImmutable

echo $date->format('d.m.Y H:i');
echo $date->diffForHumans();            // "3 дня назад"
echo $date->isToday() ? 'сегодня' : '';

// Сравнение
if ($date->gt(new Carbon('2024-01-01'))) { ... }
```

---

## 22. Работа с PHONE, EMAIL, WEB, IM (v3)

В v3 SDK поля `PHONE`, `EMAIL`, `WEB`, `IM` на Result-объектах — это **типизированные объекты**, а не сырые массивы.

### Phone[]

```php
$contact = $crm->contact()->get(456)->contact();
$phones = $contact->PHONE;   // Phone[]

foreach ($phones as $phone) {
    echo $phone->VALUE;                    // '+79001234567'
    echo $phone->VALUE_TYPE->value;        // 'WORK', 'HOME', 'MOBILE', ...
    echo $phone->ID;                       // int, внутренний ID записи
}

// Безопасное получение первого телефона
$firstPhone = !empty($phones) ? $phones[0]->VALUE : null;
```

### Email[]

```php
$emails = $contact->EMAIL;   // Email[]
foreach ($emails as $email) {
    echo $email->VALUE;           // 'test@example.com'
    echo $email->VALUE_TYPE->value;   // 'WORK', 'HOME', ...
}
```

### При записи — передавать массив (не объекты)

```php
// При add() и update() — используем обычный массив
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

// При обновлении телефонов нужно передать ID существующей записи,
// иначе будет добавлена новая вместо замены
$crm->contact()->update(456, [
    'PHONE' => [
        ['ID' => 999, 'VALUE' => '+79001112233', 'VALUE_TYPE' => 'WORK'],
    ],
]);
```

### Batch->list() — PHONE из сырого массива

При использовании `batch->list()` PHONE возвращается как типизированный `Phone[]`, поскольку данные проходят через `ContactItemResult`. Поведение одинаково с `get()`.

---

## 23. Прямые вызовы через core->call()

Для методов, не реализованных в typed-сервисах, или для передачи `auth_connector`:

```php
// Прямой вызов любого REST-метода
$response = $b24->core->call('crm.category.list', [
    'entityTypeId' => 2,  // 2 = сделки
]);

$data = $response->getResponseData()->getResult()->getData();
// $data['result'] — массив воронок

// Получить сырые данные
foreach ($data['result'] as $category) {
    echo $category['id'] . ': ' . $category['name'] . PHP_EOL;
}

// С auth_connector (для offline-событий, требует Pro тариф)
$b24->core->call('crm.deal.update', [
    'id'             => 123,
    'fields'         => ['STAGE_ID' => 'WON'],
    'auth_connector' => 'my_sync',
]);

// Bind offline event
$b24->core->call('event.bind', [
    'event'          => 'ONCRMDEALUPDATE',
    'event_type'     => 'offline',
    'auth_connector' => 'my_sync',
]);

// Получить офлайн-события
$response = $b24->core->call('event.offline.get', [
    'clear'          => 0,
    'auth_connector' => 'my_sync',
    'limit'          => 50,
]);
```

---

## 24. Типичные ошибки и их причины

### UNAUTHORIZED

```
UNAUTHORIZED request error
```
**Причина:** У вебхука не включён нужный scope.
**Решение:** Битрикс24 → Разработчикам → Входящие вебхуки → выбрать вебхук → добавить нужные права (crm, tasks и т.д.).

### Cannot use object of type Phone as array

```php
// НЕПРАВИЛЬНО (так было в v2):
$phone = $contact->PHONE[0]['VALUE'];

// ПРАВИЛЬНО (v3):
$phone = !empty($contact->PHONE) ? $contact->PHONE[0]->VALUE : null;
```

### Медленная пагинация

**Причина:** Сортировка не по ID в `batch->list()`.
**Решение:** По возможности используйте `order: ['ID' => 'ASC']`. Если нужна сортировка по дате — это медленнее, но корректно работает.

### Устаревший access_token (OAuth)

```php
// Передайте 0 в expires — SDK обновит токен через refresh_token автоматически
new AuthToken($accessToken, $refreshToken, 0);
```

### Не возвращаются UF-поля

**Причина:** UF-поля нужно явно указать в `select`.
**Решение:**
```php
$select = ['*', 'UF_CRM_MY_FIELD'];
// или
$select = ['*', 'UF_*'];  // все UF-поля
```

### Цикл при офлайн-событиях

**Причина:** Ваш скрипт обновляет сущность, что снова порождает событие.
**Решение:** Передавать `auth_connector` во все модифицирующие вызовы через `core->call()` (Pro тариф).

### Сортировка по дате в batch возвращает неполные данные

**Причина:** При медленной пагинации SDK делает запросы с `start=0, 50, 100...`. Если между запросами изменились данные — возможны пропуски.
**Решение:** Для критичных выборок по дате использовать быстрый режим с фильтром вместо сортировки:
```php
// Вместо order['DATE_MODIFY'] лучше:
$filter = ['>=DATE_MODIFY' => '2024-01-01'];
$order  = ['ID' => 'ASC'];
```

---

## 25. Получение сырых данных от API

Бывает нужно сохранить ответ API «как есть» — например, в JSON-колонку БД. SDK предоставляет два способа получить сырой PHP-массив с последующим `json_encode()`.

### Способ 1: `$b24->batch->getTraversableList()` напрямую

`getTraversableList` на уровне `Core\Batch` yield'ит **сырые PHP-массивы** — именно то, что пришло от API, до любой типизации. Typed-сервисы (`deal()->batch->list()`) только оборачивают эти массивы в DTO поверх.

```php
foreach ($b24->batch->getTraversableList(
    'crm.deal.list',
    ['ID' => 'ASC'],
    ['CATEGORY_ID' => 5],
    ['ID', 'TITLE', 'STAGE_ID', 'DATE_MODIFY', 'PHONE'],
) as $rawDeal) {
    // $rawDeal — чистый PHP-массив: ['ID' => '123', 'TITLE' => '...', ...]
    $json = json_encode($rawDeal, JSON_UNESCAPED_UNICODE);
    $db->insert('deals', ['data' => $json]);
}
```

Работает для любого метода: `crm.deal.list`, `crm.contact.list`, `crm.lead.list` и т.д.

### Способ 2: `iterator_to_array()` из типизированного объекта

`AbstractItem` (базовый класс всех DTO результатов) реализует интерфейс `IteratorAggregate`. Его метод `getIterator()` возвращает `ArrayIterator($this->data)`, где `$data` — исходный сырой массив от API.

Стандартная функция PHP `iterator_to_array()` принимает любой `Traversable`-объект и возвращает обычный массив. Это позволяет получить сырые данные из любого DTO:

```php
foreach ($crm->deal()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: ['CATEGORY_ID' => 5],
    select: ['ID', 'TITLE', 'STAGE_ID', 'DATE_MODIFY'],
) as $deal) {
    // Типизированный доступ — когда нужна бизнес-логика
    if ($deal->STAGE_ID === 'WON') {
        notifySomething($deal->ASSIGNED_BY_ID);
    }

    // Сырой массив — для записи в БД
    $rawArray = iterator_to_array($deal);  // встроенная функция PHP, не часть SDK
    $json = json_encode($rawArray, JSON_UNESCAPED_UNICODE);
    $db->insert('deals', ['data' => $json]);
}
```

`iterator_to_array()` — стандартная функция PHP (доступна с PHP 5.1), импорт не нужен.

### Что содержит сырой массив

Данные приходят **так, как их отдаёт Битрикс** — все значения строки, вложенные объекты (PHONE, EMAIL) — массивы:

```json
{
  "ID": "123",
  "TITLE": "Сделка с клиентом",
  "STAGE_ID": "C5:WON",
  "CATEGORY_ID": "5",
  "DATE_MODIFY": "2024-07-01T12:00:00+03:00",
  "ASSIGNED_BY_ID": "42",
  "PHONE": [
    {"ID": "999", "VALUE": "+79001234567", "VALUE_TYPE": "WORK"}
  ]
}
```

`ID` — строка, `DATE_MODIFY` — строка ISO 8601, `PHONE` — массив словарей (не объекты `Phone`). Это правильный формат для хранения без потерь.

### Выбор способа

| Ситуация | Способ |
|---|---|
| Нужны только сырые данные, без бизнес-логики | `$b24->batch->getTraversableList()` |
| Нужны и типизированные объекты, и сырые данные одновременно | `batch->list()` + `iterator_to_array($item)` |
