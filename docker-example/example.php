<?php

declare(strict_types=1);

use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Bitrix24\SDK\Services\Task\Service\TaskFilter;

require_once __DIR__ . '/vendor/autoload.php';

// ── Переменные окружения ─────────────────────────────────────────────────────

$webhookUrl    = getenv('B24_WEBHOOK_URL') ?: '';
$responsibleId = (int)(getenv('B24_RESPONSIBLE_ID') ?: 0);
$dateFrom      = getenv('B24_DATE_FROM') ?: date('Y-m-d', strtotime('-30 days'));

if ($webhookUrl === '') {
    echo "ОШИБКА: переменная B24_WEBHOOK_URL не задана.\n";
    echo "Укажите её в файле .env\n";
    exit(1);
}

// Маскируем токен в выводе
$maskedUrl = preg_replace('#(/rest/\d+/)[^/]+/#', '$1***/', $webhookUrl);

echo "=== Bitrix24 PHP SDK v3 — пример ===\n";
echo "Вебхук : {$maskedUrl}\n";
echo "Дата от: {$dateFrom}\n";
echo "Исполн.: " . ($responsibleId > 0 ? "ID {$responsibleId}" : 'не задан') . "\n\n";

// ── Инициализация SDK ────────────────────────────────────────────────────────
//
// ServiceBuilderFactory::createServiceBuilderFromWebhook() — единственная
// строка, необходимая для запуска. Создаёт цепочку:
//   CoreBuilder → Core (HTTP-клиент + авторизация) → Batch → ServiceBuilder
//
// ServiceBuilder — главный объект. Отдаёт scope-билдеры:
//   getTaskScope()  → TaskServiceBuilder → task(), commentitem(), ...
//   getCRMScope()   → CRMServiceBuilder  → contact(), deal(), lead(), ...
//   getMainScope()  → MainServiceBuilder → main()
//   getUserScope()  → UserServiceBuilder → user()

$b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook($webhookUrl);

// ── Проверка связи ───────────────────────────────────────────────────────────
//
// REST-метод: profile
// Документация: apidocs.bitrix24.com → main → profile

echo "──────────────────────────────────────\n";
echo "Текущий пользователь\n";
echo "──────────────────────────────────────\n";

try {
    $profile = $b24->getMainScope()->main()->getCurrentUserProfile()->getUserProfile();
    echo "  ID   : {$profile->ID}\n";
    echo "  Имя  : {$profile->NAME} {$profile->LAST_NAME}\n";
    echo "  Email: {$profile->EMAIL}\n\n";
} catch (Throwable $e) {
    echo "Ошибка подключения: " . $e->getMessage() . "\n";
    echo "Проверьте B24_WEBHOOK_URL в файле .env\n";
    exit(1);
}

// ── Задачи ───────────────────────────────────────────────────────────────────
//
// REST-метод  : tasks.task.list
// Документация: api-reference/tasks/tasks-task-list.md
// SDK-путь    : getTaskScope() → task() → batch → list()
//
// ВАЖНО: batch->list() использует старый формат фильтра с префиксами
// (аналог v1/CRM), так как внутри вызывает tasks.task.list через Batch Core.
// Поля фильтра и select — UPPER_SNAKE_CASE (как в документации API).
//
// SDK v3 также добавляет TaskFilter — type-safe билдер для REST 3.0 методов
// (task()->get(), add(), update()). Пример использования в комментарии ниже.

echo "──────────────────────────────────────\n";
echo "Задачи (batch->list, до 50 шт.)\n";
echo "──────────────────────────────────────\n";

// Формируем фильтр в традиционном формате (UPPER_SNAKE_CASE + префикс-оператор)
//   Без префикса  → = (точное совпадение)
//   Префикс >=    → больше или равно
//   Префикс !     → не равно
//   Префикс %     → LIKE (содержит)
$taskFilter = [
    '>=CREATED_DATE' => $dateFrom,  // дата создания >= $dateFrom
];
if ($responsibleId > 0) {
    $taskFilter['RESPONSIBLE_ID'] = $responsibleId;
}

// --- Альтернативный способ (v3 TaskFilter, для REST 3.0 методов): ---
//
//   use Bitrix24\SDK\Services\Task\Service\TaskFilter;
//
//   $taskFilter = (new TaskFilter())
//       ->createdDate()->gte($dateFrom)
//       ->responsibleId()->eq($responsibleId)
//       ->toArray();
//
//   TaskFilter->toArray() возвращает REST 3.0-формат:
//   [['createdDate', '>=', '2025-01-01'], ['responsibleId', '=', 42]]
//
//   Этот формат предназначен для новых v3-эндпоинтов (/rest/api/...).
//   Используйте его в task()->get(), add(), update(), но не в batch->list().
// --- конец альтернативы ---

$taskCount = 0;
try {
    // batch->list() — возвращает PHP Generator.
    // SDK сам обходит пагинацию (50 записей/страница), вы итерируете
    // по одному элементу — память не растёт с количеством записей.
    //
    // Параметры:
    //   order  — сортировка, UPPER_SNAKE_CASE
    //   filter — фильтр, UPPER_SNAKE_CASE + префиксы операторов
    //   select — нужные поля, UPPER_SNAKE_CASE
    //   limit  — максимум записей (null = без ограничений)
    foreach ($b24->getTaskScope()->task()->batch->list(
        order: ['CREATED_DATE' => 'desc'],
        filter: $taskFilter,
        select: ['ID', 'TITLE', 'CREATED_DATE', 'RESPONSIBLE_ID', 'STATUS', 'DEADLINE'],
        limit: 50
    ) as $task) {
        // Результат — объект TaskItemResult.
        // Поля доступны как camelCase-свойства (конвертация происходит внутри SDK):
        //   CREATED_DATE  → $task->createdDate  (CarbonImmutable|null)
        //   RESPONSIBLE_ID → $task->responsibleId (int|null)
        //   STATUS        → $task->status        (int|null)
        //   DEADLINE      → $task->deadline      (CarbonImmutable|null)
        $created  = $task->createdDate?->format('Y-m-d') ?? '—';
        $deadline = $task->deadline?->format('Y-m-d') ?? 'нет';
        $statusLabel = match ($task->status) {
            2       => 'ждёт',
            3       => 'в работе',
            4       => 'на проверке',
            5       => 'завершена',
            6       => 'отложена',
            default => (string)$task->status,
        };

        printf(
            "  #%-6d [%-11s] дедлайн: %-10s создана: %s  %s\n",
            $task->id,
            $statusLabel,
            $deadline,
            $created,
            mb_strimwidth($task->title, 0, 50, '…')
        );
        $taskCount++;
    }
} catch (Throwable $e) {
    $msg = $e->getMessage();
    echo "  Ошибка: {$msg}\n";
    if (stripos($msg, 'UNAUTHORIZED') !== false) {
        echo "\n  Причина: у вебхука нет доступа к scope «task».\n";
        echo "  Решение: Битрикс24 → Разработчикам → Другое → Входящий вебхук\n";
        echo "           → включите разрешение «Задачи» и сохраните.\n\n";
    }
}

echo "Итого задач: {$taskCount}\n\n";

// ── Контакты ─────────────────────────────────────────────────────────────────
//
// REST-метод  : crm.contact.list
// Документация: api-reference/crm/contacts/crm-contact-list.md
// SDK-путь    : getCRMScope() → contact() → batch → list()
//
// CRM-контакты в SDK v3 по-прежнему используют старый формат фильтра.
// Поля в ContactItemResult доступны в UPPER_SNAKE_CASE (как в API),
// кроме дат — они объекты CarbonImmutable.

echo "──────────────────────────────────────\n";
echo "Контакты (batch->list, последние 200 изменённых)\n";
echo "──────────────────────────────────────\n";

$contactCount = 0;
try {
    foreach ($b24->getCRMScope()->contact()->batch->list(
        order: ['DATE_MODIFY' => 'DESC'],  // от самых свежих к старым
        filter: [
            '>=DATE_MODIFY' => $dateFrom,
        ],
        select: ['ID', 'NAME', 'LAST_NAME', 'DATE_MODIFY', 'PHONE', 'EMAIL'],
        limit: 200
    ) as $contact) {
        // Поля ContactItemResult — UPPER_SNAKE_CASE (как в документации API).
        // DATE_MODIFY — объект CarbonImmutable.
        // PHONE, EMAIL, WEB, IM — массивы типизированных объектов (Phone[], Email[], ...)
        $modified = $contact->DATE_MODIFY?->format('Y-m-d H:i') ?? '—';
        // PHONE — массив объектов Phone (не массив массивов, как в v1).
        // Каждый объект имеет свойства: VALUE, VALUE_TYPE (PhoneValueType), ID
        $phones = $contact->PHONE;  // Phone[]
        $phone  = !empty($phones) ? ($phones[0]->VALUE ?? '—') : '—';
        $fullName = trim("{$contact->NAME} {$contact->LAST_NAME}");

        printf(
            "  #%-6d %-28s изм.: %s  тел: %s\n",
            $contact->ID,
            mb_strimwidth($fullName, 0, 28, '…'),
            $modified,
            $phone
        );
        $contactCount++;
    }
} catch (Throwable $e) {
    echo "  Ошибка: " . $e->getMessage() . "\n";
}

echo "Итого контактов: {$contactCount}\n\n";
echo "=== Готово ===\n";
