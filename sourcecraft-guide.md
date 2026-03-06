# Руководство по использованию B24PHPSDK

## Введение

B24PHPSDK — это официальная PHP-библиотека для работы с REST API Битрикс24, разработанная как современная замена устаревшему CRest. Она предоставляет строгую типизацию, автодополнение в IDE, поддержку batch-запросов и удобные объекты для данных.

В этом руководстве мы разберём, как с помощью SDK выполнять типичные задачи:
- Авторизация через вебхук.
- Получение списка задач с фильтрацией по дате создания и исполнителю.
- Получение списка контактов, изменённых за определённый период (последние 200).

## Установка

Установите SDK через Composer:

```bash
composer require bitrix24/b24phpsdk:"^3.0"
```

Требуется PHP 8.4 или выше. Для PHP 8.2–8.3 можно использовать версию 1.x.

## Авторизация через вебхук

Вебхук — это простой способ авторизации для интеграций с одним порталом. Получите URL вебхука в разделе «Разработчикам» → «Другие» → «Входящие вебхуки» вашего Битрикс24.

Пример инициализации SDK:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Bitrix24\SDK\Services\ServiceBuilderFactory;

$webhookUrl = 'https://your-portal.bitrix24.com/rest/1/your-webhook-token/';
$b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook($webhookUrl);
```

Теперь `$b24` — это точка входа для всех операций.

## Структура SDK

SDK организован по областям (scope), соответствующим разделам API Битрикс24:

- **MainScope** — общие методы (информация о приложении, пользователях).
- **CRMScope** — работа с CRM (контакты, сделки, компании и т.д.).
- **TaskScope** — управление задачами.
- **UserScope** — пользователи.
- И другие (Calendar, Disk, etc.).

Каждый scope содержит сервисы для конкретных сущностей. Например, `CRMScope` включает сервисы `contact()`, `deal()`, `lead()`.

## Работа с задачами

### Получение списка задач с фильтрацией

Допустим, нам нужны задачи, созданные за последние 30 дней и назначенные на конкретного исполнителя (ID = 547).

В документации API метод `tasks.task.list` описывает параметры `filter`, `order`, `select`. В SDK мы используем batch-объект для эффективного чтения с пагинацией.

Пример (см. также `example_tasks.php`):

```php
$taskService = $b24->getTaskScope()->task();
$batch = $taskService->batch;

$filter = [
    '>=CREATED_DATE' => date('Y-m-d', strtotime('-30 days')),
    'RESPONSIBLE_ID' => 547,
];
$order = ['CREATED_DATE' => 'DESC'];
$select = ['ID', 'TITLE', 'CREATED_DATE', 'RESPONSIBLE_ID', 'STATUS'];

$generator = $batch->getTraversableList(
    'tasks.task.list',
    $order,
    $filter,
    $select,
    null // без ограничения количества
);

foreach ($generator as $task) {
    echo "Задача #{$task['id']}: {$task['title']}\n";
}
```

**Пояснения:**

- `getTraversableList` — метод, который возвращает генератор. Он автоматически обрабатывает пагинацию, делая запросы по мере необходимости.
- Параметры `filter`, `order`, `select` полностью соответствуют документации REST API.
- Результат каждой итерации — ассоциативный массив с данными задачи (поля соответствуют `select`).

## Работа с контактами

### Получение последних 200 изменённых контактов

Нам нужны контакты, изменённые за последние 30 дней, отсортированные по дате изменения (сначала новые), но не более 200 штук.

Метод `crm.contact.list` описан в документации. Используем batch для ограничения лимита.

Пример (см. также `example_contacts.php`):

```php
$contactService = $b24->getCRMScope()->contact();
$batch = $contactService->batch;

$filter = [
    '>=DATE_MODIFY' => date('Y-m-d', strtotime('-30 days')),
];
$order = ['DATE_MODIFY' => 'DESC'];
$select = ['ID', 'NAME', 'LAST_NAME', 'DATE_MODIFY', 'EMAIL', 'PHONE'];

$generator = $batch->getTraversableList(
    'crm.contact.list',
    $order,
    $filter,
    $select,
    200 // лимит 200 записей
);

$count = 0;
foreach ($generator as $contact) {
    $count++;
    echo "Контакт #{$contact['ID']}: {$contact['NAME']} {$contact['LAST_NAME']}\n";
}
echo "Всего получено: $count\n";
```

**Особенности:**

- Поля в результате именуются так же, как в API (`ID`, `NAME` и т.д.).
- Множественные поля (`EMAIL`, `PHONE`) возвращаются как массивы с элементами `VALUE`, `VALUE_TYPE`.
- Лимит 200 означает, что SDK остановится после получения 200 записей, даже если всего их больше.

## Связь SDK с документацией API

Каждый метод SDK соответствует конкретному REST-методу Битрикс24. Например:

- `Contact::list()` → `crm.contact.list`
- `Task::batch->getTraversableList('tasks.task.list', ...)` → `tasks.task.list`

В исходном коде SDK каждый метод снабжён атрибутом `ApiEndpointMetadata`, который указывает имя REST-метода и ссылку на официальную документацию. Это позволяет легко переходить от кода к документации.

**Как найти нужный метод:**

1. Определите, к какому scope относится операция (задачи — `task`, контакты — `crm`).
2. Найдите в папке `b24phpsdk-3/src/Services/{Scope}/{Entity}/Service/` соответствующий PHP-класс.
3. Изучите методы класса (например, `list`, `add`, `update`, `delete`).
4. Посмотрите атрибут `ApiEndpointMetadata` над методом — там указано имя REST-метода.

## Batch-обработка и пагинация

REST API Битрикс24 возвращает не более 50 записей за один запрос. Чтобы получить все данные, нужно реализовать пагинацию. SDK делает это автоматически через batch-методы.

**Преимущества batch-подхода:**

- Используются PHP-генераторы, что экономит память.
- Запросы выполняются лениво — по мере итерации.
- Можно задать лимит на общее количество записей.

**Пример batch-чтения всех контактов (без лимита):**

```php
foreach ($contactService->batch->list([], [], ['ID', 'NAME']) as $contact) {
    // обрабатываем каждый контакт
}
```

## Обработка ошибок

SDK выбрасывает исключения при ошибках сети, авторизации, неверных параметрах. Рекомендуется оборачивать вызовы в try-catch.

```php
try {
    $result = $contactService->list($order, $filter, $select, 0);
} catch (\Bitrix24\SDK\Core\Exceptions\TransportException $e) {
    echo "Ошибка сети: " . $e->getMessage();
} catch (\Bitrix24\SDK\Core\Exceptions\BaseException $e) {
    echo "Ошибка API: " . $e->getMessage();
} catch (\Throwable $e) {
    echo "Неизвестная ошибка: " . $e->getMessage();
}
```

## Заключение

B24PHPSDK предоставляет мощный и удобный способ взаимодействия с API Битрикс24. Благодаря типизации, автодополнению и batch-обработке он значительно ускоряет разработку и уменьшает количество ошибок.

В этом проекте вы найдёте три готовых примера:

- `example_webhook.php` — базовая авторизация.
- `example_tasks.php` — фильтрация задач.
- `example_contacts.php` — получение последних изменённых контактов.

Используйте их как основу для своих интеграций. Подробнее о возможностях SDK читайте в официальной документации на [GitHub](https://github.com/bitrix24/b24phpsdk) и в статье на Хабре (файл `Битрикс24 PHP SDK как замена CRest для локальных и тиражных решений _ Хабр.html`).