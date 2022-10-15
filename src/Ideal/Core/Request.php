<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core;

/**
 * Класс-обёртка для работы с переменной $_REQUEST
 *
 * @property string page Зарезервировано для листалки, в переменной содержится номер запрашиваемой страницы
 * @property string action Зарезервировано для названия вызываемого экшена
 * @property string mode В случае ajax-запроса содержит 'ajax'
 * @property string controller Принудительное указание вызываемого контроллера
 */
class Request
{
    /**
     * Магический метод для извлечения значения $name из $_REQUEST
     *
     * Если в $_REQUEST параметр $name отсутствует, то будет возвращена пустая строка.
     *
     * @param string $name Название параметра
     *
     * @return string Значение этого параметра в $_REQUEST
     */
    public function __get(string $name)
    {
        // Перенос в $_REQUEST значений из formValues (используется исключительно для работы в админке)
        if (isset($_REQUEST['formValues'])) {
            parse_str($_REQUEST['formValues'], $values);
            $_REQUEST = array_merge($_REQUEST, $values);
            unset($_REQUEST['formValues']);
        }

        return $_REQUEST[$name] ?? '';
    }

    /**
     * Установка значения в переменную $_REQUEST. Настоятельно не рекомендуется это делать
     *
     * @param string $name Название параметра, который нужно задать
     * @param mixed $value Значение параметра
     */
    public function __set(string $name, $value)
    {
        $_REQUEST[$name] = $value;
    }

    /**
     * Проверка на существование переменной в $_REQUEST с помощью функции isset()
     *
     * @param string $name Название переменной
     *
     * @return bool
     */
    public function __isset(string $name)
    {
        return isset($_REQUEST[$name]);
    }

    /**
     * Получение значения переменной из GET-параметра (без экранирования)
     *
     * @param string $name Название GET-параметра
     *
     * @return mixed Если параметр не задан, вернёт пустую строку
     */
    public function get(string $name, string $default = '')
    {
        return $_GET[$name] ?? $default;
    }

    public function set(string $name, string $value): void
    {
        $_GET[$name] = $value;
    }

    /**
     * Получение из query string строки за исключением параметра $without и его значения
     *
     * @param string $without Параметр, который нужно исключить из query string
     * @param string $url Полный адрес вызываемой страницы
     *
     * @return string Query string без параметра $without
     */
    public function getQueryWithout(string $without, string $url = ''): string
    {
        $url = empty($url) ? $_SERVER['REQUEST_URI'] : $url;
        // Убираем переменную $without стоящую внутри GET-строки
        $uri = preg_replace('/' . $without . '=(.*)(&|$)/iU', '', $url);
        // Убираем переменную $without в конце строки
        $uri = preg_replace('/' . $without . '=(.*)(&|$)/iU', '', $uri);
        // Убираем последний амперсанд, если остался после предыдущих операций
        $uri = preg_replace('/&$/', '', $uri);
        // Убираем последний знак вопроса, если остался после предыдущих операций
        return preg_replace('/\?$/', '', $uri);
    }
}
