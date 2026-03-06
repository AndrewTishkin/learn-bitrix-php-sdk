# Bitrix24 PHP SDK v3 — ядро: установка, авторизация, batch, фильтры

## Оглавление

1. [Установка](#1-установка)
2. [Авторизация: вебхук](#2-авторизация-вебхук)
3. [Авторизация: OAuth-приложение](#3-авторизация-oauth-приложение)
4. [Архитектура SDK](#4-архитектура-sdk)
5. [Batch и пагинация](#5-batch-и-пагинация)
6. [Фильтры и операторы](#6-фильтры-и-операторы)
7. [Работа с результатами](#7-работа-с-результатами)
8. [Получение сырых данных](#8-получение-сырых-данных)
9. [Прямые вызовы core->call()](#9-прямые-вызовы-core-call)
10. [Типичные ошибки](#10-типичные-ошибки)
11. [SortOrder — порядок сортировки REST 3.0](#11-sortorder--порядок-сортировки-rest-30)
12. [SelectBuilder — построитель полей выборки](#12-selectbuilder--построитель-полей-выборки)
13. [OAuth-исключения](#13-oauth-исключения)

---

## 1. Установка

**Требования:** PHP >= 8.4, `ext-curl`, `ext-intl`

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

```php
require_once __DIR__ . '/vendor/autoload.php';
```

---

## 2. Авторизация: вебхук

Простейший способ. Подходит для одного портала без офлайн-событий.

```php
use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Psr\Log\NullLogger;

$b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook(
    getenv('B24_WEBHOOK_URL'),  // https://portal.bitrix24.com/rest/1/token/
    new EventDispatcher(),
    new NullLogger()
);

// Проверка соединения
$user = $b24->getMainScope()->main()->getCurrentUserProfile()->user();
echo $user->NAME . ' ' . $user->LAST_NAME;
```

Для вебхука нужно включить права в Битрикс24:
`Разработчикам → Входящие вебхуки → [вебхук] → выбрать scope (crm, task и т.д.)`

---

## 3. Авторизация: OAuth-приложение

Нужно для офлайн-событий, работы с несколькими порталами, тиражных приложений.

**Создание локального приложения:**
`Битрикс24 → Разработчикам → Другое → Локальное приложение → Создать`
- Тип: Серверное
- Использует только API: ✅
- Путь для установки: `https://your-app.example.com/install.php`

**install.php — получение первых токенов:**

```php
// Битрикс24 делает POST на этот URL при создании приложения
$auth = $_POST['auth'];

file_put_contents('tokens.json', json_encode([
    'access_token'    => $auth['access_token'],
    'refresh_token'   => $auth['refresh_token'],  // сохранить навсегда
    'client_endpoint' => $auth['client_endpoint'],
    'domain'          => $auth['domain'] ?? '',
]));

// Здесь же регистрируем нужные события (event.bind и т.д.)
```

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

$tokens = json_decode(file_get_contents('tokens.json'), true);

$b24 = (new ServiceBuilderFactory(new EventDispatcher(), new NullLogger()))->init(
    $appProfile,
    new AuthToken(
        $tokens['access_token'],
        $tokens['refresh_token'],
        0  // 0 = считать истёкшим, SDK обновит автоматически
    ),
    $tokens['client_endpoint'],
    'https://oauth.bitrix.info/'
);
// SDK сам обновляет access_token (живёт 1 час) через refresh_token
```

---

## 4. Архитектура SDK

```
ServiceBuilderFactory::createServiceBuilderFromWebhook(url)
  └─ $b24  (ServiceBuilder)
       ├─ getCRMScope()    → CRMServiceBuilder   → deal(), contact(), lead(), ...
       ├─ getTaskScope()   → TaskServiceBuilder  → task(), checklistitem(), ...
       ├─ getMainScope()   → MainServiceBuilder  → main()
       ├─ getUserScope()   → UserServiceBuilder  → user()
       └─ core             → CoreInterface       → call(method, params)
            └─ batch       → BatchOperationsInterface → getTraversableList(...)
```

Каждый сервис (deal, contact, task…):
- Методы одиночных запросов: `get()`, `add()`, `update()`, `delete()`, `list()`
- `->batch` — сервис для batch-операций с автопагинацией

---

## 5. Batch и пагинация

### Автопагинация через batch->list()

```php
// Перебирает ВСЕ записи автоматически, без ручного управления страницами
foreach ($b24->getCRMScope()->deal()->batch->list(
    order:  ['ID' => 'ASC'],
    filter: ['CATEGORY_ID' => 5],
    select: ['ID', 'TITLE', 'STAGE_ID'],
    limit:  null   // null = без ограничений, int = максимум N записей
) as $deal) {
    // $deal — типизированный DealItemResult
}
```

### Два режима пагинации (выбираются автоматически)

**Быстрый** — когда `order` пустой или первое поле = `ID`:
```
Первый запрос: start=0 → получаем 50 элементов + total
Затем batch из до 50 подзапросов, каждый ссылается на ID последнего элемента предыдущего:
  cmd_0: filter[>ID]=<last_id>&start=-1
  cmd_1: filter[>ID]=$result[cmd_0][49][ID]&start=-1
  ...
Один HTTP-запрос → до 50×50=2500 элементов
```

**Медленный** — при сортировке по любому другому полю (`DATE_MODIFY`, `TITLE`, …):
```
Использует start=0, 50, 100... — стандартная пагинация
SDK логирует: getTraversableList.unoptimalParams
```

Предпочитайте `order: ['ID' => 'ASC']` для массовых выборок.

### Прямой вызов getTraversableList

Для методов, которых нет в typed-сервисах:

```php
foreach ($b24->batch->getTraversableList(
    'tasks.task.list',
    ['ID' => 'ASC'],
    ['RESPONSIBLE_ID' => 5],
    ['ID', 'TITLE', 'STATUS'],
    100  // лимит
) as $rawTask) {
    // $rawTask — сырой PHP-массив
}
```

---

## 6. Фильтры и операторы

Оператор — **префикс к имени поля** в ключе массива фильтра:

```php
$filter = [
    'FIELD'     => 'value',    // = точное совпадение
    '!FIELD'    => 'value',    // != не равно
    '>FIELD'    => 100,        // > больше
    '>=FIELD'   => 100,        // >= больше или равно
    '<FIELD'    => 500,        // < меньше
    '<=FIELD'   => 500,        // <= меньше или равно
    '%FIELD'    => 'abc',      // LIKE %abc%
    'FIELD'     => [1, 2, 3],  // IN (одно из значений)
    '!FIELD'    => [1, 2, 3],  // NOT IN
];
```

### Фильтр по дате

```php
$filter = [
    '>=DATE_MODIFY' => '2024-01-01',
    '<DATE_MODIFY'  => '2024-07-01',
    '>=DATE_CREATE' => (new DateTime('-30 days'))->format('Y-m-d'),
];
```

### Поле select

```php
$select = ['ID', 'TITLE', 'STAGE_ID'];   // только нужные поля (быстро)
$select = ['*'];                           // все поля (медленно, для отладки)
$select = ['*', 'UF_CRM_MY_FIELD'];       // стандартные + конкретное UF-поле
$select = ['*', 'UF_*'];                  // стандартные + все UF-поля
```

---

## 7. Работа с результатами

### Одиночный результат

```php
// get() → Result-объект → DTO
$deal = $b24->getCRMScope()->deal()->get(123)->deal();
echo $deal->ID;           // int
echo $deal->TITLE;        // string
echo $deal->DATE_MODIFY->format('d.m.Y');  // CarbonImmutable

// add() → ID созданного элемента
$id = $b24->getCRMScope()->deal()->add([...])->getId();   // int

// update() / delete() → bool
$ok = $b24->getCRMScope()->deal()->update(123, [...])->isSuccess();
```

### Список (одна страница без пагинации)

```php
$result = $b24->getCRMScope()->deal()->list([], [], ['ID', 'TITLE'], 0);
$deals  = $result->getDeals();   // DealItemResult[]
$total  = $result->getCoreResponse()->getResponseData()->getPagination()->getTotal();
$next   = $result->getCoreResponse()->getResponseData()->getPagination()->getNextItem();
```

### CarbonImmutable (даты)

```php
$date = $deal->DATE_MODIFY;          // CarbonImmutable
echo $date->format('d.m.Y H:i');
echo $date->diffForHumans();         // "3 дня назад"
$date->isToday();
$date->gt(new Carbon('2024-01-01'));
```

---

## 8. Получение сырых данных

### Способ 1: getTraversableList — чистые массивы

`$b24->batch->getTraversableList()` yield'ит сырые PHP-массивы до любой типизации:

```php
foreach ($b24->batch->getTraversableList(
    'crm.deal.list',
    ['ID' => 'ASC'],
    ['CATEGORY_ID' => 5],
    ['ID', 'TITLE', 'STAGE_ID', 'DATE_MODIFY', 'PHONE'],
) as $rawDeal) {
    // ['ID' => '123', 'TITLE' => '...', 'DATE_MODIFY' => '2024-...', ...]
    $json = json_encode($rawDeal, JSON_UNESCAPED_UNICODE);
    $db->insert('deals', ['payload' => $json]);
}
```

### Способ 2: iterator_to_array() из DTO

Все DTO наследуют `AbstractItem`, который реализует `IteratorAggregate`.
`getIterator()` возвращает `ArrayIterator($this->data)` — исходный сырой массив.
`iterator_to_array()` — стандартная функция PHP (с 5.1), импорт не нужен:

```php
foreach ($b24->getCRMScope()->deal()->batch->list(
    order: ['ID' => 'ASC'], filter: [...], select: [...]
) as $deal) {
    // Типизированный доступ для логики
    if ($deal->CLOSED) {
        continue;
    }

    // Сырой массив для хранения
    $rawArray = iterator_to_array($deal);
    $json = json_encode($rawArray, JSON_UNESCAPED_UNICODE);
}
```

### Что содержит сырой массив

Все значения — **строки**, вложенные объекты (PHONE, EMAIL) — массивы словарей:
```json
{
  "ID": "123",
  "TITLE": "Сделка",
  "STAGE_ID": "C5:WON",
  "DATE_MODIFY": "2024-07-01T12:00:00+03:00",
  "PHONE": [{"ID": "999", "VALUE": "+79001234567", "VALUE_TYPE": "WORK"}]
}
```

| Ситуация | Способ |
|---|---|
| Только сырые данные, без логики | `batch->getTraversableList()` |
| Типизированные объекты + сырые данные | `batch->list()` + `iterator_to_array($item)` |

---

## 9. Прямые вызовы core->call()

Для методов вне typed-сервисов или для передачи `auth_connector`:

```php
// Любой REST-метод
$response = $b24->core->call('crm.category.list', ['entityTypeId' => 2]);
$data = $response->getResponseData()->getResult();
// $data — массив с ключом 'result' (или сразу данные, зависит от метода)

// С auth_connector (предотвращение цикла офлайн-событий, Pro тариф)
$b24->core->call('crm.deal.update', [
    'id'             => 123,
    'fields'         => ['STAGE_ID' => 'WON'],
    'auth_connector' => 'my_sync',
]);
```

Typed-методы (`deal()->update()`) `auth_connector` не поддерживают — только `core->call()`.

---

## 10. Типичные ошибки

### UNAUTHORIZED request error

Вебхук не имеет нужного scope.
Решение: `Разработчикам → Входящие вебхуки → [вебхук] → добавить права`.

### Cannot use object of type Phone as array

В v3 `PHONE`, `EMAIL`, `WEB`, `IM` — объекты, не массивы.
```php
// Неправильно (v2-стиль):
$phone = $contact->PHONE[0]['VALUE'];
// Правильно (v3):
$phone = !empty($contact->PHONE) ? $contact->PHONE[0]->VALUE : null;
```

### Медленная пагинация

Сортировка не по ID в `batch->list()`. Используйте `order: ['ID' => 'ASC']`.

### Устаревший access_token (OAuth)

```php
new AuthToken($accessToken, $refreshToken, 0);  // 0 = SDK обновит сразу
```

### UF-поля не возвращаются

```php
$select = ['*', 'UF_CRM_MY_FIELD'];  // или ['*', 'UF_*']
```

### Сортировка по дате → пропуски в данных

При медленной пагинации данные могут меняться между запросами.
Лучше: фильтр по дате + сортировка по ID:
```php
filter: ['>=DATE_MODIFY' => '2024-01-01'],
order:  ['ID' => 'ASC']
```

---

## 11. SortOrder — порядок сортировки REST 3.0

В REST 3.0 сортировка задаётся через enum `SortOrder`, а не строками:

```php
use Bitrix24\SDK\Core\Contracts\SortOrder;

// SortOrder::Ascending  = 'ASC'
// SortOrder::Descending = 'DESC'

// Использование в list() и tail() методах REST 3.0
$result = $eventLog->list(
    select:     $select,
    filter:     $filter,
    order:      ['id' => SortOrder::Descending],
    pagination: ['limit' => 50]
);
```

> **Заметка:** В REST 1.x/2.x (CRM, Task и большинство методов) сортировка задаётся строками
> `['ID' => 'ASC']`. `SortOrder` нужен только для REST 3.0 методов (EventLog, и т.д.).

---

## 12. SelectBuilder — построитель полей выборки

`SelectBuilder` — типобезопасный способ указать, какие поля вернуть в REST 3.0 запросе.

### Базовый принцип

```php
// Только нужные поля
$select = (new TaskItemSelectBuilder())
    ->id()
    ->title()
    ->creatorId();

// Все системные поля (через рефлексию)
$select = (new TaskItemSelectBuilder())->allSystemFields();

// Системные поля + пользовательские
$select = (new TaskItemSelectBuilder())
    ->allSystemFields()
    ->withUserFields(['UF_CRM_TASK', 'UF_MY_FIELD']);
```

### TaskItemSelectBuilder

Для задач (scope `task`, REST 3.0):

```php
use Bitrix24\SDK\Services\Task\Service\TaskItemSelectBuilder;

$select = (new TaskItemSelectBuilder())
    ->id()           // всегда включается в конструкторе
    ->title()
    ->description()
    ->creatorId()
    ->creator()      // добавляет chat.id + chat.entityId
    ->created()
    ->chat();        // chat.id + chat.entityId
```

### EventLogSelectBuilder

Для журнала аудита (scope `main`, REST 3.0):

```php
use Bitrix24\SDK\Services\Main\Service\EventLogSelectBuilder;

$select = (new EventLogSelectBuilder())
    ->id()
    ->timestampX()
    ->severity()
    ->userId()
    ->auditTypeId()
    ->moduleId()
    ->description()
    ->allSystemFields();  // все поля сразу
```

### Метод allSystemFields()

`allSystemFields()` использует PHP-рефлексию: вызывает все публичные методы без параметров у конкретного подкласса. Результат — `SelectBuilder` с максимальным набором системных полей.

---

## 13. OAuth-исключения

Полная таблица исключений, которые SDK выбрасывает при OAuth-ошибках:

| Исключение | Причина | Действие |
|---|---|---|
| `InvalidGrantException` | Refresh token истёк или недействителен | Попросить пользователя заново авторизоваться |
| `PortalDomainNotFoundException` | HTTP 404 — портал не найден (удалён или заблокирован) | Деактивировать аккаунт |
| `WrongClientException` | HTTP 401 `invalid_client` — неверный client_id/client_secret | Проверить credentials приложения |
| `WrongSecuritySignatureException` | Подпись события не совпала | Отклонить запрос (403) |
| `PaymentRequiredException` | Требуется оплата тарифа для этого метода | Уведомить пользователя |
| `QueryLimitExceededException` | Превышен лимит запросов к REST API | Сделать паузу и повторить |
| `OperationTimeLimitExceededException` | Операция выполняется слишком долго | Уменьшить объём запроса |
| `MethodConfirmWaitingException` | Метод ожидает подтверждения пользователем | Обработать через UI |
| `UserNotFoundOrIsNotActiveException` | Пользователь не найден или неактивен | Проверить права/активность |

### Пример обработки

```php
use Bitrix24\SDK\Core\Exceptions\InvalidGrantException;
use Bitrix24\SDK\Core\Exceptions\PortalDomainNotFoundException;
use Bitrix24\SDK\Core\Exceptions\WrongClientException;
use Bitrix24\SDK\Core\Exceptions\QueryLimitExceededException;

try {
    $result = $b24->getCRMScope()->deal()->get(123);
} catch (InvalidGrantException $e) {
    // Refresh token истёк — нужна повторная авторизация
    $this->markAccountAsNeedsReauth($portalId);
} catch (PortalDomainNotFoundException $e) {
    // Портал удалён или недоступен
    $this->deactivatePortal($portalId);
} catch (WrongClientException $e) {
    // Ошибка конфигурации приложения
    $this->logger->critical('OAuth credentials invalid', ['exception' => $e]);
} catch (QueryLimitExceededException $e) {
    // Превышен лимит — подождать и повторить
    sleep(2);
    // retry...
}
```
