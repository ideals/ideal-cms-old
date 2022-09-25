<?php
namespace Ideal;

ini_set('display_errors', 'On');

// Абсолютный адрес корня сервера, не должен оканчиваться на слэш.
define('DOCUMENT_ROOT', getenv('SITE_ROOT') ?: $_SERVER['DOCUMENT_ROOT']);

require DOCUMENT_ROOT . '/../vendor/autoload.php';

// Инициализируем фронт контроллер
$page = new Core\FrontController();

$page->run();
