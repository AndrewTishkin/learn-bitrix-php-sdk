<?php

declare(strict_types=1);

require_once __DIR__ . '/b24phpsdk-3/vendor/autoload.php';

use Bitrix24\SDK\Services\ServiceBuilderFactory;

/**
 * Пример получения списка контактов с фильтрацией по дате изменения (последние 200 изменённых).
 *
 * Фильтры:
 * - DATE_MODIFY >= определённая дата (например, за последние 30 дней)
 * - Сортировка по DATE_MODIFY DESC (чтобы получить последние изменённые)
 * - Ограничение выборки 200 записей.
 *
 * Запуск: php example_contacts.php
 */

$webhookUrl = 'https://your-portal.bitrix24.com/rest/1/your-webhook-token/';

try {
    $b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook($webhookUrl);

    // Получаем сервис контактов через CRMScope
    $contactService = $b24->getCRMScope()->contact();

    // Параметры фильтрации: контакты, изменённые за последние 30 дней
    $filter = [
        '>=DATE_MODIFY' => date('Y-m-d', strtotime('-30 days')),
    ];

    // Сортировка по дате изменения (убывание) - самые свежие первыми
    $order = [
        'DATE_MODIFY' => 'DESC',
    ];

    // Выбираемые поля
    $select = [
        'ID',
        'NAME',
        'LAST_NAME',
        'DATE_MODIFY',
        'DATE_CREATE',
        'ASSIGNED_BY_ID',
        'EMAIL',
        'PHONE',
        'TYPE_ID',
        'SOURCE_ID',
    ];

    // Ограничение количества записей (максимум 200)
    $limit = 200;

    // Для получения списка контактов используем метод list сервиса Contact
    // Метод list требует параметры order, filter, select, start
    // Чтобы получить первые 200 записей, нужно выполнить пагинацию.
    // Используем batch для удобного получения с лимитом.
    $batch = $contactService->batch;

    $generator = $batch->getTraversableList(
        'crm.contact.list',
        $order,
        $filter,
        $select,
        $limit
    );

    $count = 0;
    echo "Список контактов (последние 200 изменённых):\n";
    foreach ($generator as $contact) {
        $count++;
        echo "--- Контакт #{$count} ---\n";
        echo "ID: " . ($contact['ID'] ?? 'N/A') . "\n";
        echo "Имя: " . ($contact['NAME'] ?? 'N/A') . "\n";
        echo "Фамилия: " . ($contact['LAST_NAME'] ?? 'N/A') . "\n";
        echo "Дата изменения: " . ($contact['DATE_MODIFY'] ?? 'N/A') . "\n";
        echo "Дата создания: " . ($contact['DATE_CREATE'] ?? 'N/A') . "\n";
        echo "Ответственный ID: " . ($contact['ASSIGNED_BY_ID'] ?? 'N/A') . "\n";
        echo "Тип: " . ($contact['TYPE_ID'] ?? 'N/A') . "\n";
        echo "Источник: " . ($contact['SOURCE_ID'] ?? 'N/A') . "\n";

        // Email (множественное поле)
        if (isset($contact['EMAIL']) && is_array($contact['EMAIL'])) {
            $emails = array_map(function ($email) {
                return $email['VALUE'] ?? '';
            }, $contact['EMAIL']);
            echo "Email: " . implode(', ', $emails) . "\n";
        } else {
            echo "Email: N/A\n";
        }

        // Телефон (множественное поле)
        if (isset($contact['PHONE']) && is_array($contact['PHONE'])) {
            $phones = array_map(function ($phone) {
                return $phone['VALUE'] ?? '';
            }, $contact['PHONE']);
            echo "Телефон: " . implode(', ', $phones) . "\n";
        } else {
            echo "Телефон: N/A\n";
        }

        echo "\n";
    }

    echo "Всего получено контактов: {$count}\n";

} catch (\Throwable $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Подробности: " . $e->getTraceAsString() . "\n";
}