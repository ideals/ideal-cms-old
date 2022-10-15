<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Service\UpdateCms;

use Exception;
use Ideal\Core\Config;
use RuntimeException;

/**
 * Класс для работы с версиями Ideal CMS
 *
 * Позволяет получать версии с сервера обновлений, а также извлекает установленные версии
 * из файла обновлений и из файла README.md установленной версии Ideal CMS
 */
class Versions
{
    /** @var array Ответ, возвращаемый при ajax-вызове */
    protected array $answer = ['message' => [], 'error' => false, 'data' => null];

    /** @var string Путь к файлу с логом обновлений */
    protected $log = '';

    /**
     * Инициализация файла лога обновлений
     * @throws Exception
     * @noinspection MultipleReturnStatementsInspection
     */
    public function __construct()
    {
        $config = Config::getInstance();
        // Файл лога обновлений
        $log = $config->rootDir . '/' . $config->cms['tmpFolder'] . '/update.log';
        // Проверяем существует ли файл лога
        if (!file_exists($log)) {
            $this->addAnswer('Файл лога обновлений не существует ' . $log, 'info');
            $path = dirname($log);
            // Если нет прав на запись в папку, и не удалось их получить, завершаем скрипт
            if (!is_writable($path) && !chmod($path, intval($config->cms['dirMode'], 8))) {
                $this->addAnswer('Нет удалось получить права на запись в папку ' . $path, 'error');
                $this->log = false;
                return;
            }
            // Пытаемся создать файл
            if (file_put_contents($log, '') !== false) {
                $this->addAnswer('Файл лога обновлений создан ', 'info');
            } else {
                $this->addAnswer('Не удалось создать файл лога обновлений ' . $log, 'error');
                $this->log = false;
                return;
            }
        } elseif (!is_writable($log) && !chmod($log, intval($config->cms['fileMode'], 8))) {
                // Если нет прав на запись в файл лога обновлений и получить их не удалось
                $this->addAnswer('Файл ' . $log . ' недоступен для записи', 'error');
                $this->log = false;
                return;
        }
        $this->log = $log;
    }

    /**
     * Получение версии админки, а также наименований модулей и их версий
     *
     * @return array Массив с номерами установленных версий
     * @throws Exception
     */
    public function getVersions(): array
    {
        $config = Config::getInstance();
        // Путь к файлу README.md для cms
        $mods['Ideal-CMS'] = $config->cmsDir;

        // Ищем файлы README.md в модулях
        $modDirName = DOCUMENT_ROOT . '/' . $config->cmsFolder . '/Mods';
        if (file_exists($modDirName)) {
            // Получаем папки
            $modDirs = array_diff(scandir($modDirName), ['.', '..']); // получаем массив папок модулей
            foreach ($modDirs as $dir) {
                // Исключаем папки, явно не содержащие модули
                if ((strncasecmp($dir, '.', 1) === 0) || (is_file($modDirName . '/' . $dir))) {
                    unset($mods[$dir]);
                    continue;
                }
                $mods[$dir] = $modDirName . '/' . $dir;
            }
        }
        // Получаем версии для каждого модуля и CMS из update.log
        return $this->getVersionFromFile($mods);
    }

    /**
     * Получение версии из файла
     *
     * @param array $mods Папки с модулями и CMS
     * @return array Версия CMS и модулей
     * @throws Exception
     */
    protected function getVersionFromFile(array $mods): array
    {
        // Получаем версии из файлов README
        $version = $this->getVersionFromReadme($mods);

        if ($version === null) {
            throw new RuntimeException('Произошла ошибка в определении версий модулей');
        }

        if (filesize($this->log) === 0) {
            // Если update.log нет, создаём его
            $this->putVersionLog($version, $this->log);
        } else {
            // Если лог есть, проверяем все ли модули прописаны в нём
            $versionLog = $this->getVersionFromLog($this->log);

            foreach ($version as $mod => $ver) {
                if (isset($versionLog[$mod])) {
                    continue;
                }
                // Если этот модуль не записан в лог, добавляем его к списку
                $versionLog[$mod] = $ver;
                $this->writeLog('Installed ' . $mod . ' v.' . $ver);
            }
            $version = $versionLog;
        }
        return $version;
    }

    /**
     * Получение версий из Readme.md
     *
     * @param array $mods Массив состоящий из названий модулей и полных путей к ним
     * @return null|array Версии модулей или false в случае ошибки
     * @throws Exception
     * @noinspection MultipleReturnStatementsInspection
     */
    public function getVersionFromReadme(array $mods): ?array
    {
        // Получаем файл README.md для cms
        $mdFile = 'README.md';
        $version = [];
        foreach ($mods as $k => $v) {
            if (!file_exists($v . '/' . $mdFile)) {
                $this->addAnswer('Отсутствует файл ' . $v . '/' . $mdFile, 'error');
                return null;
            }
            $lines = file($v . '/' . $mdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false || count($lines) === 0) {
                $this->addAnswer('Не удалось получить версию из ' . $v . '/' . $mdFile, 'error');
                return null;
            }
            // Получаем номер версии из первой строки
            // Формат номера: пробел+v.+пробел+номер-версии+пробел-или-конец-строки
            preg_match_all('/\sv\.(\s*)(.*)(\s*)/i', $lines[0], $ver);
            // Если номер версии не удалось определить — выходим
            if (!isset($ver[2][0]) || ($ver[2][0] === '')) {
                $this->addAnswer('Ошибка при разборе строки с версией файла', 'error');
                return null;
            }

            $version[$k] = $ver[2][0];
        }

        return $version;
    }

    /**
     * Запись версий в update.log
     *
     * @param array $version Версии полученные из Readme.ms
     * @param string $log     Файл с логом обновлений
     */
    protected function putVersionLog(array $version, string $log): void
    {
        $lines = [];
        foreach ($version as $k => $v) {
            $lines[] = 'Installed ' . $k . ' v.' . $v;
        }
        file_put_contents($log, implode("\n", $lines) . "\n");
    }

    /**
     * Получение версий из файла update.log
     *
     * @param string $log Файл с логом обновлений
     * @return null|array Версии модулей и обновлений
     * @throws Exception
     */
    protected function getVersionFromLog(string $log): ?array
    {
        $linesLog = file($log);
        $versions = [];

        foreach ($linesLog as $v) {
            // Удаление спец символов конца строки (если пролез символ \r)
            $v = rtrim($v);
            if (strncmp($v, 'Installed ', 10) === 0) {
                // Строка содержит сведения об установленном модуле
                $v = substr($v, 10);
                $name = substr($v, 0, strpos($v, ' '));
                // Формат номера: пробел+v.+пробел+номер-версии+пробел-или-конец-строки
                preg_match_all('/\sv\.(\s*)(.*)(\s*)/i', $v, $ver);
                // Если номер версии не удалось определить — выходим
                if (!isset($ver[2][0]) || ($ver[2][0] === '')) {
                    $this->addAnswer('Ошибка при разборе строки с версией файла', 'error');
                    return null;
                }

                $versions[$name] = $ver[2][0];
            }
        }

        return $versions;
    }

    /**
     * Добавление сообщения, возвращаемого в ответ на ajax запрос
     *
     * @param string|array $message Сообщения возвращаемые в ответ на ajax запрос
     * @param string $type Статус сообщения, характеризующий так же наличие ошибки
     * @param mixed $data Данные передаваемые в ответ на ajax запрос
     * @throws Exception
     */
    public function addAnswer($message, string $type, $data = null): void
    {
        if (!is_string($message)) {
            throw new RuntimeException('Необходим аргумент типа строка');
        }
        if (!in_array($type, ['error', 'info', 'warning', 'success'])) {
            throw new RuntimeException('Недопустимое значение типа сообщения');
        }
        $this->answer['message'][] = [$message, $type];
        if ($type === 'error') {
            $this->answer['error'] = true;
        }
        if ($data !== null) {
            $this->answer['data'] = $data;
        }
    }

    /**
     * Получение результирующих данных
     *
     * @return array
     */
    public function getAnswer(): array
    {
        return $this->answer;
    }

    /**
     * Получение пути к файлу с логом обновлений
     *
     * @return string Путь к файлу с логом обновлений
     */
    public function getLogName()
    {
        return $this->log;
    }

    /**
     * Запись строки в log-файл
     *
     * @param string $msg Строка для записи в log
     */
    public function writeLog(string $msg): void
    {
        $msg = rtrim($msg) . "\n";
        file_put_contents($this->log, $msg, FILE_APPEND);
    }
}
