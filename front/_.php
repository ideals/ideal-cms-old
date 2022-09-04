<?php
namespace Ideal;

use Ideal\Structure\User\Admin\Plugin;

ini_set('display_errors', 'On');

// Абсолютный адрес корня сервера, не должен оканчиваться на слэш.
define('DOCUMENT_ROOT', getenv('SITE_ROOT') ?: $_SERVER['DOCUMENT_ROOT']);

require DOCUMENT_ROOT . '/../vendor/autoload.php';

// Подключаем класс конфига
$config = Core\Config::getInstance();

// Загружаем список структур из конфигурационных файлов структур
$config->loadSettings(DOCUMENT_ROOT . '/..');

if (isset($isConsole)) {
    // Если инициализированная переменная $isConsole, значит этот скрипт используется
    // только для инициализации окружения
    return;
}

// Инициализируем фронт контроллер
$page = new Core\FrontController();

if (strpos($_SERVER['REQUEST_URI'], 'api/') === 1) {
    // Обращение к api
    $page->run('api');
} elseif (strpos($_SERVER['REQUEST_URI'], $config->cmsFolder . '/') === 1) {
    // Обращение к административной части

    // Регистрируем плагин авторизации
    $pluginBroker = Core\PluginBroker::getInstance();
    $pluginBroker->registerPlugin('onPostDispatch', Plugin::class);

    // Запускаем фронт контроллер
    $page->run('admin');
} else {
    // Обращение к пользовательской части
    $page->run('site');
}
