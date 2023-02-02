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
use Ideal\Core\Db;
use Ideal\Core\Util;
use RuntimeException;
use ZipArchive;

/**
 * Получение номеров версий установленной CMS и модулей
 *
 */
class Model
{

    /** @var array Ответ, возвращаемый при ajax-вызове */
    protected array $answer = ['message' => [], 'error' => false, 'data' => null];

    /** @var string Путь к файлу с логом обновлений */
    protected $log = '';

    /** @var bool Признак тестового режима */
    protected bool $testMode = false;

    /** @var array Массив папок для обновления */
    protected array $updateFolders = [];

    /** @var string Название модуля */
    public string $updateName = '';

    /** @var string Версия, на которую производится обновление */
    public string $updateVersion = '';

    /** @var string Текущая версия */
    public string $currentVersion = '';

    /**
     * Инициализация файла лога обновлений
     */
    public function __construct()
    {
        $versions = new Versions();
        // Получаем название файла лога
        $this->log = $versions->getLogName();
        // Получаем сообщения, если возникли проблемы при работе с файлом лога
        if ($this->log === false) {
            $this->answer = $this->getAnswer();
            exit;
        }
    }

    /**
     * Задаём признак тестового режима
     *
     * @param bool $testMode Признак тестового режима
     */
    public function setTestMode(bool $testMode): void
    {
        $this->testMode = $testMode;
    }

    /**
     * Задаём название и версию обновляемого модуля
     *
     * @param string $updateName    Название модуля
     * @param string $updateVersion Номер версии, на которую обновляемся
     * @param string $currentVersion Номер текущей версии
     */
    public function setUpdate(string $updateName, string $updateVersion, string $currentVersion): void
    {
        // todo Сделать защиту от хакеров на POST-переменные
        $this->updateName = $updateName;
        $this->updateVersion = $updateVersion;
        $this->currentVersion = $currentVersion;
    }

    /**
     * Загрузка архива с обновлениями
     * @throws Exception
     */
    public function downloadUpdate()
    {
        $updateUrl = $this->updateFolders['getFileScript']
            . '?name=' . urlencode(serialize($this->updateName))
            . '&cVer=' . $this->currentVersion
            . '&ver=' . $this->updateVersion;
        $info = json_decode(file_get_contents($updateUrl), true, 512, JSON_THROW_ON_ERROR);

        // Проверка на получение данных о получаемом обновлении
        if (!isset($info['file'], $info['md5'], $info['version']) || $info === false) {
            $this->addAnswer(
                'Не удалось получить данные о получаемом обновлении',
                'error'
            );
            exit;
        }

        // Название файла для сохранения
        $path = $this->updateFolders['uploadDir'] . '/' . $this->updateName;

        $fp = fopen($path, 'wb');

        $updateUrl = $this->updateFolders['getFileScript']
            . '?file=' . $info['file'];

        $ch = curl_init($updateUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        $data = curl_exec($ch);

        curl_close($ch);
        fclose($fp);


        // Проверка на получение файла
        if ($data === false) {
            $this->addAnswer(
                'Не удалось получить файл обновления с сервера обновлений ' . $this->updateFolders['getFileScript'],
                'error'
            );
            exit;
        }
        // Проверка создан ли запрошенный файл
        if (!file_exists($path)) {
            $this->addAnswer('Не удалось создать файл.', 'error');
            exit;
        }

        if (md5_file($path) !== $info['md5']) {
            $this->addAnswer('Полученный файл повреждён (хеш не совпадает)', 'error');
            exit;
        }

        $this->addAnswer('Загружен архив с обновлениями', 'success');
        // Возвращаем название загруженного архива
        return ['path' => $path, 'version' => $info['version']];
    }

    /**
     * Распаковка архива
     *
     * @param array $archive Полный путь к файлу архива с новой версии
     * @return void
     * @throws Exception
     */
    public function unpackUpdate(array $archive): void
    {
        $zip = new ZipArchive();
        $res = $zip->open($archive['path']);

        if ($res !== true) {
            $this->addAnswer('Не получилось из-за ошибки #' . $res, 'error');
            exit;
        }

        // Очищаем папку перед распаковкой в неё файлов
        $this->removeDirectory(SETUP_DIR, true);

        // Распаковываем архив в папку
        $zip->extractTo(SETUP_DIR);
        $zip->close();
        unlink($archive['path']);
        $this->addAnswer('Распакован архив с обновлениями', 'success');
    }


    /**
     * Замена каталога со старой версией на каталог с новой версией
     *
     * @return string Путь к старому разделу
     * @throws Exception
     */
    public function swapUpdate(): string
    {
        // Определяем путь к тому что мы обновляем, cms или модули
        $config = Config::getInstance();
        if ($this->updateName === 'Ideal-CMS') {
            // Путь к cms
            $updateCore = DOCUMENT_ROOT . '/' . $config->cmsFolder . '/' . 'Ideal';
        } else {
            // Путь к модулям
            $updateCore = DOCUMENT_ROOT . '/' . $config->cmsFolder . '/' . 'Mods' . '/' . $this->updateName;
        }
        // Переименовываем папку, которую собираемся заменить
        if (!rename($updateCore, $updateCore . '_old')) {
            $this->addAnswer('Не удалось переименовать папку ' . $updateCore, 'error');
            exit;
        }
        // Перемещаем новую папку на место старой
        if (!rename(SETUP_DIR, $updateCore)) {
            $this->addAnswer('Не удалось переименовать папку ' . $updateCore, 'error');
            exit;
        }

        $result = Util::chmod($updateCore, $config->cms['dirMode'], $config->cms['fileMode']);

        if (count($result) !== 0) {
            // Объединяем все пути, для которых не удалось изменить права в одну строку
            $paths = array_reduce(
                $result,
                static function (&$result, $item) {
                    $result .= "<br />\n" . $item['path'];
                }
            );
            $this->addAnswer(
                "Не удалось изменить права для следующих файлов/папок: <br />\n$paths",
                'warning'
            );
        }
        $this->addAnswer('Заменены файлы', 'success');
        return $updateCore . '_old';
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
     * Удаление папки или её очистка
     *
     * @param string $dir   Папка которую необходимо удалить или очистить
     * @param bool $clear Если значение ложь, то удаляем папку, если истина, очищаем
     * @return bool
     * @noinspection MultipleReturnStatementsInspection
     */
    public function removeDirectory(string $dir, bool $clear = false): bool
    {
        $res = true;
        if (!file_exists($dir)) {
            // Если папки нет, то и удалять её не надо, а если требовалось очистить - возвращаем ошибку
            return !$clear;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $res = (is_dir("$dir/$file")) ? $this->removeDirectory("$dir/$file") : unlink("$dir/$file");
        }
        if (!$clear) {
            return rmdir($dir);
        }
        return $res;
    }

    /**
     * Установка путей к папкам для обновления модулей
     *
     * @param $array
     */
    public function setUpdateFolders($array): void
    {
        $this->updateFolders = [
            'getFileScript' => $array['getFileScript'],
            'uploadDir' => $array['uploadDir']
        ];
    }

    /**
     * Получение списка скриптов
     *
     * @return array
     * @throws Exception
     */
    public function getUpdateScripts(): array
    {
        // Находим путь к последнему установленному скрипту модуля
        $logFile = file($this->log);
        $updatePath = $this->updateName . '/setup/update';
        $updateFolder = SETUP_DIR . '/setup/update';
        $lastScript = '';
        foreach ($logFile as $v) {
            if (strpos($v, $updatePath) === 0) {
                $lastScript = str_replace($updatePath, '', trim($v));
            }
        }

        if ($lastScript !== '') {
            // Находим номер версии и название файла последнего установленного скрипта
            $currentVersion = basename(dirname($lastScript));
        } else {
            $version = new Versions();
            $versions = $version->getVersions(); // получаем список установленных модулей
            $currentVersion = $versions[$this->updateName];
        }

        // Считываем названия папок со скриптами обновления
        $updates = array_diff(scandir($updateFolder), ['.', '..']);

        // Убираем из списка файлы
        foreach ($updates as $k => $v) {
            if (!is_dir($updateFolder . '/' . $v)) {
                unset($updates[$k]);
            }
        }

        // Сортируем папки по номерам версий
        usort(
            $updates,
            static function ($a, $b) {
                return version_compare($a, $b);
            }
        );

        // Убираем из списка папки с установленными обновлениями
        foreach ($updates as $k => $v) {
            if (version_compare($v, $currentVersion) < 0) {
                unset($updates[$k]);
            }
        }

        // Составление списка скриптов для обновления
        $scripts = ['pre' => [], 'after' => []];
        foreach ($updates as $folder) {
            $scriptFolder = $updateFolder . '/' . $folder;
            $files = array_diff(scandir($scriptFolder), ['.', '..']);
            foreach ($files as $file) {
                $fileScript = '/' . $folder . '/' . $file;
                if (is_dir($scriptFolder . '/' . $file)) {
                    continue;
                }
                if ($lastScript === $fileScript) {
                    // Нашли последний установленный скрипт, значит отсекаем все предыдущие скрипты
                    $scripts = ['pre' => [], 'after' => []];
                    continue;
                }
                if (preg_match('(\/new_\)', $fileScript) && version_compare($folder, $currentVersion) > 0) {
                    $scripts['after'][] = $fileScript;
                } else {
                    $scripts['pre'][] = $fileScript;
                }
            }
        }

        $this->addAnswer(
            'Получен список скриптов в количестве: ' . (count($scripts['pre']) + count($scripts['after'])),
            'success',
            ['scripts' => json_encode($scripts, JSON_THROW_ON_ERROR)]
        );

        return $scripts;
    }

    /**
     * Запуск скрипта обновления
     *
     * @param string $script
     * @throws Exception
     */
    public function runScript(string $script): void
    {
        // Производим запуск скриптов обновления
        $db = Db::getInstance();
        $ext = substr($script, strrpos($script, '.'));

        if (strncmp(basename($script), 'new', 3) === 0) {
            $file = 'setup/update' .  $script;
            if ($this->updateName !== 'Ideal-CMS') {
                $file = $this->updateName . '/' . $file;
            }
        } else {
            $file = SETUP_DIR . '/setup/update' . $script;
        }
        $fileForLog = $this->updateName . '/setup/update' . $script;

        $text = '';
        switch ($ext) {
            case '.php':
                ob_start();
                include $file;
                $text = ob_get_clean();
                $text = ($text !== '') ? "\n<br />Скрипт выдал: " . $text : '';
                break;
            case '.sql':
                $query = file_get_contents($file . $script);
                if (!$db->query($query)) {
                    $this->addAnswer('Ошибка при выполнении sql скрипта: ' . $file, 'error');
                }
                break;
            default:
                return;
        }
        if (!$this->testMode) {
            $this->writeLog($fileForLog);
        }
        $this->addAnswer('Выполнен скрипт: ' . $script . $text, 'success');
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
