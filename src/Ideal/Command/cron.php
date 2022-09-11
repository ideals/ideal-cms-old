<?php
// Подключаем Composer
require_once __DIR__ .  '/../../../../../vendor/autoload.php';

$cron = new \Ideal\Structure\Service\Cron\Crontab();

// Если запуск тестовый, то выполняем только необходимые тесты
if (isset($argv[1]) && $argv[1] === 'test') {
    $cron->testAction();
    echo $cron->getMessage();
} else {
    $cron->runAction();
}
