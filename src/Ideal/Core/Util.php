<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core;

use Exception;
use FB;
use Ideal\Mailer;

/**
 * Класс полезных функций
 *
 */
class Util
{
    /** @var array Массив для хранения списка ошибок, возникших при выполнении скрипта */
    public static array $errorArray = [];

    /**
     * Вывод сообщения об ошибке
     *
     * @param string $txt Текст сообщения об ошибке
     * @param bool $isTrace
     */
    public static function addError(string $txt, bool $isTrace = true): void
    {
        $config = Config::getInstance();
        if (empty($config->cms['errorLog'])) {
            return;
        }
        $trace = [];
        $traceStrBr = '';
        $traceStr = '';
        if ($isTrace) {
            // Если нужно вывести путь до места совершения ошибки, строим его
            $traceList = debug_backtrace();
            array_shift($traceList); // убираем информацию о методе добавления ошибки
            foreach ($traceList as $item) {
                $file = str_replace($_SERVER['DOCUMENT_ROOT'], '', $item['file']);
                $trace[] = '#' . $item['line'] . ' in ' . $file . ' function ' . $item['function'] . PHP_EOL;
            }

            $traceStr = PHP_EOL . 'Trace:' . PHP_EOL . implode(PHP_EOL, $trace);
            $traceStrBr = PHP_EOL . 'Trace:' . PHP_EOL . implode('<br>', $trace);
        }
        switch ($config->cms['errorLog']) {
            case 'file':
                // Вывод сообщения в текстовый файл
                $msg = date('d.m.y H:i') . '  ' . $_SERVER['REQUEST_URI'] . PHP_EOL;
                $msg .= $txt . $traceStr . PHP_EOL . PHP_EOL;
                $file = DOCUMENT_ROOT . '/' . $config->cmsFolder . '/error.log';
                file_put_contents($file, $msg, FILE_APPEND);
                break;

            case 'display':
                // При возникновении ошибки, тут же её выводим на экран
                print $txt . $traceStrBr . '<br />';
                break;

            case 'comment':
                // При возникновении ошибки, выводим её в комментарии
                print '<!-- ' . $txt . $traceStr . ' -->' . PHP_EOL;
                break;

            case 'firebug':
                // Отображаем ошибку для просмотра через FireBug
                array_unshift($trace, $txt);
                try {
                    FB::error($trace);
                } /** @noinspection BadExceptionsProcessingInspection */ catch (Exception $e) {
                    // todo разобраться, что тут за ошибка может быть
                }
                break;

            case 'email':
            case 'var':
                self::$errorArray[] = $txt . $traceStr;
                break;

            default:
                break;
        }
    }

    /**
     * Конвертация текст из кодировки базы в кодировку сайта (обычно cp1251->UTF8)
     *
     * @param string $text Строка в кодировке сайта (обычно cp1251)
     * @return string Строка в кодировке базы (обычно UTF8)
     */
    public static function convertFromDb(string $text): string
    {
        $config = Config::getInstance();
        $siteCharset = 'UTF-8';

        if (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, $siteCharset, $config->db['charset']);
        } elseif (function_exists('iconv')) {
            $text = iconv($config->db['charset'], $siteCharset, $text);
        }

        return $text;
    }

    /**
     * Конвертация текст из кодировки сайта в кодировку базы (обычно UTF8->cp1251)
     *
     * @param string $text Строка в кодировке сайта (обычно UTF8)
     * @return string Строка в кодировке базы (обычно cp1251)
     */
    public static function convertToDb(string $text): string
    {
        $config = Config::getInstance();
        $siteCharset = 'UTF-8';

        if (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, $config->db['charset'], $siteCharset);
        } elseif (function_exists('iconv')) {
            $text = iconv($siteCharset, $config->db['charset'], $text);
        }

        return $text;
    }

    /**
     * Возвращает отформатированную дату, с названием месяца на русском
     *
     * @param int $date Дата в формате timestamp
     * @param string $year Окончание даты (по умолчанию добавляем ` года`
     *
     * @return string Строка с отформатированной датой
     */
    public static function dateReach(int $date, string $year = ' года'): string
    {
        $months = [
            '',
            'января',
            'февраля',
            'марта',
            'апреля',
            'мая',
            'июня',
            'июля',
            'августа',
            'сентября',
            'октября',
            'ноября',
            'декабря'
        ];
        return date('j', $date) . ' ' . $months[date('n', $date)] . ' ' .
            date('Y', $date) . $year;
    }

    /**
     * Возвращает отформатированную дату из даты в текстовом формате, с названием месяца на русском
     *
     * @param string $date Дата в текстовом формате
     * @return string Строка с отформатированной датой
     */
    public static function dateStrReach(string $date): string
    {
        $months = [
            '',
            'января',
            'февраля',
            'марта',
            'апреля',
            'мая',
            'июня',
            'июля',
            'августа',
            'сентября',
            'октября',
            'ноября',
            'декабря'
        ];
        $dateArr = explode(' ', $date);
        $dateArr = explode('-', $dateArr[0]);
        $day = (int)$dateArr[1];

        return $dateArr[2] . ' ' . $months[$day] . ' ' . $dateArr[0] . ' года';
    }

    /**
     * Получение полного названия класса структуры/поля/аддона на основании краткого названия
     *
     * @param string $module Краткое название класса (например, Ideal_Part)
     * @param string $type Тип класса (например, Structure или Field)
     * @return string
     */
    public static function getClassName(string $module, string $type): string
    {
        [$module, $structure] = explode('_', $module);
        return '\\' . $module . '\\' . $type . '\\' . $structure;
    }

    /**
     * Проверяет правильно ли написан адрес электронной почты
     *
     * @param string $mail - адрес электронной почты
     * @return bool - истина, если ящик написан правильно
     */
    public static function isEmail(string $mail): bool
    {
        // Проверяем правильно ли в мыле поставлены знаки @ и .
        $posAT = strpos($mail, '@');
        $posDOT = strrpos($mail, '.');
        if (($posAT < 1) || ($posDOT < 3) || ($posAT > $posDOT)) {
            return false;
        }

        $isEmail = true;

        //Проверяем, нет ли в мыле русских букв
        $len = strlen($mail);
        for ($i = 0; $i < $len; $i++) {
            if (ord($mail[$i]) > 127) {
                $isEmail = false;
                break;
            }
        }

        return $isEmail;
    }

    /**
     * Обрабатываем блок текста, переданный из браузера
     *
     * @param string $str строка
     * @param int $len Максимальная длина строки (по умолчанию 3072)
     * @return string Безопасный блок текста
     */
    public static function parseWebArea(string $str, int $len = 3072): string
    {
        // Обрезаем строку до нужного размера
        $str = mb_substr($str, 0, $len);
        // Заменяем все спец. символы на их html-сущности
        $str = htmlspecialchars($str, ENT_QUOTES); // преобразуются и двойные и одинарные кавычки

        // Превращаем все переводы строки в <br>
        $str = nl2br($str);

        // Убираем переводы строк и возврат каретки.
        // Заменяем @ на собаку, в блоке текста этот символ совершенно не нужен
        return str_replace(["\n", "\r", '@'], ['', '', '[собака]'], $str);
    }

    /**
     * Обрабатываем строку, переданную из браузера
     *
     * @param string $str строка
     * @param int $len Максимальная длина строки (по умолчанию 255)
     * @return string Безопасная строка
     */
    public static function parseWebStr(string $str, int $len = 255): string
    {
        $str = self::parseWebMail($str, $len);
        // Заменяем @ на собаку, в обычном тексте этот символ совершенно не нужен
        return str_replace('@', '[собака]', $str);
    }

    /**
     * Обработка адрес e-mail, переданного из браузера
     *
     * @param string $str E-mail
     * @param int $len Максимальная длина строки (по умолчанию 255)
     * @return string Безопасный и валидный адрес
     */
    public static function parseWebMail(string $str, int $len = 255): string
    {
        // Считается, что передаётся одна строка, поэтому всё,
        // Что идёт за переводом строки - это хакеры
        $arr = explode("\n", $str);
        $str = $arr[0];
        $arr = explode("\r", $str);
        $str = $arr[0];
        // Обрезаем строку до нужного размера
        $str = substr($str, 0, $len);
        // Заменяем все спец. символы на их html-сущности.
        // Преобразуются и двойные и одинарные кавычки
        return htmlspecialchars($str, ENT_QUOTES);
    }

    /**
     * Метод, вызываемый после всех действий при завершении выполнения скрипта
     * @return void
     * @throws Exception
     */
    public static function shutDown(): void
    {
        $config = Config::getInstance();
        if ($config->cms['errorLog'] === 'email' && count(self::$errorArray) > 0) {
            if (empty($_SERVER['REQUEST_URI'])) {
                // Ошибка произошла при выполнении скрипта в консоли
                $source = 'При выполнении скрипта ' . $_SERVER['PHP_SELF'];
            } else {
                // Ошибка произошла при выполнении скрипта в браузере
                $protocol = $config->getProtocol();
                $source = 'На странице ' . $protocol . $config->domain . $_SERVER['REQUEST_URI'];
            }

            $text = "Здравствуйте!\n\n$source произошли следующие ошибки.\n\n"
                . implode("\n\n", self::$errorArray) . "\n\n"
                . '$_SERVER = ' . "\n" . print_r($_SERVER, true) . "\n\n";
            if (isset($_GET)) {
                $text .= '$_GET = ' . "\n" . print_r($_GET, true) . "\n\n";
            }
            if (isset($_POST)) {
                $text .= '$_POST = ' . "\n" . print_r($_POST, true) . "\n\n";
            }
            if (isset($_COOKIE)) {
                $text .= '$_COOKIE = ' . "\n" . print_r($_COOKIE, true) . "\n\n";
            }
            $subject = 'Сообщение об ошибке на сайте ' . $config->domain;
            $mail = new Mailer();
            $mail->setSubj($subject);
            $mail->setPlainBody($text);
            $mail->sent($config->robotEmail, $config->cms['adminEmail']);
        }
    }

    /**
     * Обрезает строку $str до длинны $len и убирает все символы в конце
     * строки до последнего пробела
     *
     * @param string $str Исходная строка
     * @param int $len Максимальное количество символов в строке
     * @return string
     */
    public static function smartTrim(string $str, int $len): string
    {
        $firstLen = mb_strlen($str);
        $str = mb_substr($str, 0, $len);
        if ($firstLen !== mb_strlen($str)) {
            $str = mb_substr($str, 0, mb_strrpos($str, ' '));
        }
        return $str;
    }

    /**
     * Переход на страницу логина с сохранением страницы, на которую не пустило
     *
     * @param string $link Ссылка на страницу авторизации
     */
    public function goUrl(string $link): void
    {
        $_SESSION['prev_post'] = serialize($_POST);
        $_SESSION['prev_uri'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $link);
        exit;
    }

    /**
     * Генерирует одну или несколько случайных латинских букв (больших или маленьких)
     *
     * @param int $len Количество символов
     *
     * @return string Латинская буква - большая или маленькая
     * @throws Exception
     */
    public static function randomChar(int $len = 1): string
    {
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $chr1 = chr(random_int(65, 90));
            $chr2 = chr(random_int(97, 122));
            $is = random_int(0, 1);
            $str .= $is === 0 ? $str .= $chr1 :$chr2;
        }

        return $str;
    }

    /**
     * Рекурсивно изменяет права доступа к папке и всем её подкаталогам и файлам
     *
     * @param string $path Путь к папке или файлу
     * @param string $dirMode Права доступа к папке "0755"
     * @param string $fileMode Права доступа к файлу "0644"
     * @return array Содержит информацию о неудачных попытках изменения прав доступа
     * @noinspection MultipleReturnStatementsInspection
     */
    public static function chmod(string $path, string $dirMode, string $fileMode): array
    {
        $resultInfo = [];

        if (is_dir($path)) {
            if (!chmod($path, intval($dirMode, 8))) {
                return ['path' => $path, 'mode' => $dirMode, 'is_dir' => true];
            }
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $fullPath = $path . '/' . $file;
                $arr = self::chmod($fullPath, $dirMode, $fileMode);
                foreach ($arr as $item) {
                    $resultInfo[] = $item;
                }
            }
        } else {
            if (is_link($path)) {
                return [];
            }
            if (!chmod($path, intval($fileMode, 8))) {
                $resultInfo[] = ['path' => $path, 'mode' => $fileMode, 'is_dir' => false];
            }
        }

        return $resultInfo;
    }

    /**
     * Пытается получить cid из google analytics
     *
     * @return string cid из google analytics или false в случае неудачи
     */
    public static function getGaCid(): string
    {
        $GACid = false;
        if (isset($_COOKIE['_ga'])) {
            [, , $cid1, $cid2] = explode('.', $_COOKIE['_ga'], 4);
            //[$version, $domainDepth, $cid1, $cid2] = explode('.', $_COOKIE['_ga'], 4);
            //$contents = ['version' => $version, 'domainDepth' => $domainDepth, 'cid' => $cid1 . '.' . $cid2];
            $GACid = $cid1 . '.' . $cid2;
        }
        return $GACid;
    }
}
