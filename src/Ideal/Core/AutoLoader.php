<?php

use Ideal\Core\Util;

error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING); //| E_STRICT

setlocale(LC_ALL, 'ru_RU.UTF8');

// Для PHP5 нужно установить часовой пояс
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Europe/Moscow');
}

/**
 * Обработчик обычных ошибок скриптов. Реакция зависит от настроек $config->errorLog
 *
 * @param int $errno Номер ошибки
 * @param string $errstr Сообщение об ошибке
 * @param string $errfile Имя файла, в котором была ошибка
 * @param int $errline Номер строки на которой произошла ошибка
 * @throws Exception
 * @noinspection PhpMissingParamTypeInspection
 */
function myErrorHandler($errno, $errstr, $errfile, $errline)
{
    $_err = 'Ошибка [' . $errno . '] ' . $errstr . ', в строке ' . $errline . ' файла ' . $errfile;
    Util::addError($_err);
}

set_error_handler('myErrorHandler');

/**
 * Обработчик, вызываемый при завершении работы скрипта.
 * Используется для обработки ошибок, которые не перехватывает set_error_handler()
 * Реакция зависит от настроек $config->errorLog
 * @throws Exception
 */
function shutDownFunction()
{
    $error = error_get_last();
    $errors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING];
    if ($error !== null && in_array($error['type'], $errors, true)) {
        $_err = 'Ошибка ' . $error['message'] . ', в строке ' . $error['line'] . ' файла ' . $error['file'];
        Util::addError($_err, false);
    }
    Util::shutDown();
}

register_shutdown_function('shutdownFunction');

mb_internal_encoding('UTF-8'); // наша кодировка всегда UTF-8
