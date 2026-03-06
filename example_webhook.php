<?php

declare(strict_types=1);

require_once __DIR__ . '/b24phpsdk-3/vendor/autoload.php';

use Bitrix24\SDK\Services\ServiceBuilderFactory;

/**
 * Пример авторизации через вебхук в Битрикс24 с использованием B24PHPSDK.
 *
 * 1. Получите URL вебхука из вашего портала Битрикс24:
 *    - Перейдите в раздел "Разработчикам" -> "Другие" -> "Входящие вебхуки".
 *    - Создайте вебхук с нужными правами (например, task, crm).
 *    - Скопируйте URL вебхука.
 *
 * 2. Подставьте его в переменную $webhookUrl ниже.
 *
 * 3. Запустите скрипт: php example_webhook.php
 */

$webhookUrl = 'https://your-portal.bitrix24.com/rest/1/your-webhook-token/';

try {
    // Создание сервис-билдера из вебхука
    $b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook($webhookUrl);

    // Проверка подключения: запрос информации о приложении
    $appInfo = $b24->getMainScope()->main()->getApplicationInfo()->applicationInfo();
    echo "Информация о приложении:\n";
    print_r($appInfo);

    // Проверка текущего пользователя
    $userProfile = $b24->getMainScope()->main()->getCurrentUserProfile()->getUserProfile();
    echo "\nТекущий пользователь:\n";
    print_r($userProfile);

    // Пример вызова любого метода через core (например, user.current)
    $userCurrent = $b24->core->call('user.current');
    echo "\nМетод user.current:\n";
    print_r($userCurrent->getResponseData()->getResult());

} catch (\Throwable $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Подробности: " . $e->getTraceAsString() . "\n";
}