<?php

declare(strict_types=1);

require_once __DIR__ . '/b24phpsdk-3/vendor/autoload.php';

use Bitrix24\SDK\Services\ServiceBuilderFactory;

/**
 * Пример получения списка задач с фильтрацией по дате создания и исполнителю.
 *
 * Фильтры:
 * - CREATED_DATE >= определённая дата (например, за последние 30 дней)
 * - RESPONSIBLE_ID = ID исполнителя (например, 547)
 *
 * Дополнительно можно добавить сортировку по дате создания (убывание) и выбрать нужные поля.
 *
 * Запуск: php example_tasks.php
 */

$webhookUrl = 'https://your-portal.bitrix24.com/rest/1/your-webhook-token/';

try {
    $b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook($webhookUrl);

    // Получаем сервис задач через TaskScope
    $taskService = $b24->getTaskScope()->task();

    // Параметры фильтрации
    $filter = [
        '>=CREATED_DATE' => date('Y-m-d', strtotime('-30 days')), // задачи созданы за последние 30 дней
        'RESPONSIBLE_ID' => 547, // ID исполнителя (замените на реальный)
    ];

    // Сортировка по дате создания (убывание)
    $order = [
        'CREATED_DATE' => 'DESC',
    ];

    // Выбираемые поля
    $select = [
        'ID',
        'TITLE',
        'DESCRIPTION',
        'CREATED_DATE',
        'RESPONSIBLE_ID',
        'DEADLINE',
        'STATUS',
        'PRIORITY',
    ];

    // Параметры (опционально)
    $params = [
        'WITH_TIMER_INFO' => true,
        'WITH_RESULT_INFO' => true,
    ];

    // Вызов метода tasks.task.list через core, так как в SDK v3 нет прямого метода list в Task сервисе
    // Используем batch для получения списка с поддержкой пагинации
    $batch = $taskService->batch;

    // Получаем генератор для итерации по задачам
    $generator = $batch->getTraversableList(
        'tasks.task.list',
        $order,
        $filter,
        $select,
        null, // limit (null - без ограничения)
        $params
    );

    $count = 0;
    echo "Список задач:\n";
    foreach ($generator as $task) {
        $count++;
        echo "--- Задача #{$count} ---\n";
        echo "ID: " . ($task['id'] ?? 'N/A') . "\n";
        echo "Название: " . ($task['title'] ?? 'N/A') . "\n";
        echo "Дата создания: " . ($task['createdDate'] ?? 'N/A') . "\n";
        echo "Исполнитель ID: " . ($task['responsibleId'] ?? 'N/A') . "\n";
        echo "Статус: " . ($task['status'] ?? 'N/A') . "\n";
        echo "Приоритет: " . ($task['priority'] ?? 'N/A') . "\n";
        echo "Крайний срок: " . ($task['deadline'] ?? 'N/A') . "\n";
        echo "\n";
    }

    echo "Всего найдено задач: {$count}\n";

} catch (\Throwable $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Подробности: " . $e->getTraceAsString() . "\n";
}