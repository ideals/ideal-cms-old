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
use JsonException;

/**
 * Обновление IdealCMS или одного модуля
 *
 */
class AjaxController extends \Ideal\Core\Admin\AjaxController
{
    /** @var string Сервер обновлений */
    protected string $srv = 'https://idealcms.ru/update';

    /** @var Model  */
    protected Model $updateModel;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $config = Config::getInstance();
        $this->updateModel = new Model();

        $getFileScript = $this->srv . '/getNext.php';


        if ($config->cms['tmpFolder'] === null || ($config->cms['tmpFolder'] === '')) {
            $this->updateModel->addAnswer('В настройках не указана папка для хранения временных файлов', 'error');
            exit;
        }

        // Папка для хранения загруженных файлов обновлений
        $uploadDir = DOCUMENT_ROOT . $config->cms['tmpFolder'] . '/update';
        if (!file_exists($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $this->updateModel->addAnswer('Не удалось создать папку' . $uploadDir, 'error');
            exit;
        }

        // Папка для разархивации файлов новой CMS
        // Пример /www/example.com/tmp/setup/Update
        define('SETUP_DIR', $uploadDir . '/setup');
        if (!file_exists(SETUP_DIR)
            && !mkdir($concurrentDirectory = SETUP_DIR, 0755, true)
            && !is_dir($concurrentDirectory)
        ) {
            $this->updateModel->addAnswer('Не удалось создать папку' . SETUP_DIR, 'error');
            exit;
        }

        $this->updateModel->setUpdateFolders(
            compact('getFileScript', 'uploadDir')
        );

        // Создаём сессию для хранения данных между ajax запросами
        if (session_id() === '') {
            session_start();
        }

        if (!isset($_POST['version'], $_POST['name'])) {
            $this->updateModel->addAnswer('Непонятно, что обновлять. Не указаны version и name', 'error');
            exit;
        }

        $this->updateModel->setUpdate($_POST['name'], $_POST['version'], $_POST['currentVersion']);

        if (isset($_SESSION['update'])) {
            if ($_SESSION['update']['name'] !== $this->updateModel->updateName ||
                $_SESSION['update']['version'] !== $this->updateModel->updateVersion) {
                unset($_SESSION['update']);
            }
        }
        if (!isset($_SESSION['update'])) {
            $_SESSION['update'] = [
                'name' => $this->updateModel->updateName,
                'version' => $this->updateModel->updateVersion,
            ];
        }
    }

    /**
     * Загрузка архива с обновлениями
     * @throws Exception
     */
    public function ajaxDownloadAction(): void
    {
        // Скачиваем архив с обновлениями
        $_SESSION['update']['archive'] = $this->updateModel->downloadUpdate();
        exit;
    }

    // Распаковка архива с обновлением

    /**
     * @throws Exception
     */
    public function ajaxUnpackAction(): void
    {
        $archive = $_SESSION['update']['archive'] ?? null;
        if (!$archive) {
            $this->updateModel->addAnswer('Не получен путь к файлу архива', 'error');
            exit;
        }
        $this->updateModel->unpackUpdate($archive);
        exit;
    }

    /**
     * Получение скриптов, которые необходимо выполнить для перехода на новую версию
     * @throws Exception
     */
    public function ajaxGetUpdateScriptAction(): void
    {
        // Запускаем выполнение скриптов и запросов
        $_SESSION['update']['scripts'] = $this->updateModel->getUpdateScripts();
        exit;
    }

    /**
     * Замена старого каталога на новый
     * @throws Exception
     */
    public function ajaxSwapAction(): void
    {
        $_SESSION['update']['oldFolder'] = $this->updateModel->swapUpdate();
        exit;
    }

    /**
     * Выполнение одного скрипта из списка полученных скриптов
     * @throws Exception
     */
    public function ajaxRunScriptAction(): void
    {
        if (!isset($_SESSION['update']['scripts'])) {
            exit;
        }
        $scripts = &$_SESSION['update']['scripts'];

        // Проверяем, есть ли скрипты, которые нужно выполнить до замены файлов админки
        if (isset($scripts['pre']) && count($scripts['pre']) > 0) {
            $scriptFile = array_shift($scripts['pre']);
        } elseif (isset($scripts['after']) && count($scripts['after']) > 0) {
            $scriptFile = array_shift($scripts['after']);
        } else {
            exit;
        }

        // Запускаем выполнение скриптов и запросов
        $displayErrors = ini_get('display_errors');
        ini_set('display_errors', 'On');
        $this->updateModel->runScript($scriptFile);
        ini_set('display_errors', $displayErrors);
        exit;
    }

    /**
     *
     * @throws Exception
     */
    public function ajaxEndVersionAction(): void
    {
        // Записываем текущую версию в сессию
        $_SESSION['update']['currentVersion'] = $_SESSION['update']['archive']['version'];
        // Модуль установился успешно, делаем запись в лог обновлений
        $this->updateModel->writeLog(
            'Installed ' . $this->updateModel->updateName . ' v. ' . $_SESSION['update']['currentVersion']
        );

        // Получаем раздел со старой версией
        $oldFolder = $_SESSION['update']['oldFolder'] ?? null;
        if (!$oldFolder) {
            $this->updateModel->addAnswer('Не удалось удалить раздел со старой версией.', 'warning');
        }
        // Удаляем старую папку
        $this->updateModel->removeDirectory($oldFolder);
        $data = null;
        if ($_SESSION['update']['archive']['version'] !== $this->updateModel->updateVersion) {
            $data = ['next' => 'true', 'currentVersion' => $_SESSION['update']['currentVersion']];
        }
        $this->updateModel->addAnswer(
            'Обновление на версию ' . $_SESSION['update']['currentVersion'] . ' произведено успешно',
            'success',
            $data
        );
        exit;
    }

    /**
     * Последний этап выполнения обновления
     * @throws Exception
     */
    public function ajaxFinishAction(): void
    {
        $this->updateModel->addAnswer('Обновление завершено успешно', 'success');
        exit;
    }

    /**
     * @throws JsonException
     */
    public function __destruct()
    {
        $result = $this->updateModel->getAnswer();
        echo json_encode($result, JSON_THROW_ON_ERROR);
    }
}
