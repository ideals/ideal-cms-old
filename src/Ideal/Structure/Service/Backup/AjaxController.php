<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Service\Backup;

use Exception;
use Ideal\Core\AjaxController as CoreAjaxController;
use Ideal\Core\Config;
use Ideal\Core\Db;
use Ideal\Structure\Service\UpdateCms\Versions;
use Ifsnop\Mysqldump\Mysqldump;
use PclZip;

/**
 * Команды бэкапа базы данных
 *
 */
class AjaxController extends CoreAjaxController
{
    /**
     * Создаём дамп базы данных
     * @throws Exception
     */
    public function createDumpAction()
    {
        if (isset($_POST['createMysqlDump'])) {
            $config = Config::getInstance();

            // Папка сохранения дампов
            $backupPart = stream_resolve_include_path($_POST['backupPart']);

            // Задаём параметры для создания бэкапа
            $dumpSettings = [
                'compress' => 'GZIP',
                'no-data' => false,
                'add-drop-table' => true,
                'single-transaction' => false,
                'lock-tables' => false,
                'add-locks' => true,
                'extended-insert' => false
            ];
            $dump = new Mysqldump(
                'mysql:host=' . $config->db['host'] . ';dbname=' . $config->db['name'],
                $config->db['login'],
                $config->db['password'],
                $dumpSettings
            );

            $time = time();

            // Получаем версию админки
            $versions = new Versions();
            $nowVersions = $versions->getVersions();
            $version = 'v' . $nowVersions['Ideal-CMS'];

            // Имя файла дампа
            $dumpName = 'dump_' . date('Y.m.d_H.i.s', $time) . '_' . $version . '.sql.gz';

            // Запускаем процесс выгрузки
            $dump->start($backupPart . DIRECTORY_SEPARATOR . $dumpName);

            $dumpName = $backupPart . DIRECTORY_SEPARATOR . $dumpName;

            // Формируем строку с новым файлом
            echo '<tr id="' . $dumpName . '"><td><a href="" onClick="return downloadDump(\'' .
                addslashes($dumpName) . '\')"> ' .
                date('d.m.Y - H:i:s', $time) . ' - ' . $version
                . '</a></td>';
            echo '<td>'
                . '<button class="btn btn-info btn-xs" title="Импортировать" onclick="importDump(\'' .
                addslashes($dumpName) . '\'); return false;">'
                . '<span class="glyphicon glyphicon-upload"></span></button>&nbsp;'

                . '<button class="btn btn-danger btn-xs" title="Удалить" onclick="delDump(\'' .
                addslashes($dumpName) . '\'); return false;">'
                . '<span class="glyphicon glyphicon-remove"></span></button>&nbsp;';

            echo '</td></tr>';
        }

        exit(false);
    }

    /**
     * Удаление файла
     */
    public function deleteAction()
    {
        if (!isset($_POST['name'])) {
            echo 'Ошибка: нет имени файла для удаления';
        }

        $dumpName = stream_resolve_include_path($_POST['name']);
        // Удаляем файл дампа БД
        if (file_exists($dumpName)) {
            unlink($dumpName);
        }

        exit;
    }

    /**
     * Скачивание файла
     */
    public function downloadAction()
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/force-download');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        if (!isset($_GET['file'])) {
            header('Content-Disposition: attachment; filename=file-name-not-set');
            header('Content-Length: 0');
            exit;
        }

        if (!is_file($_GET['file'])) {
            header('Content-Disposition: attachment; filename=NOT-FOUND-' . basename($_GET['file']));
            header('Content-Length: 0');
            exit;
        }

        header('Content-Disposition: attachment; filename=' . basename($_GET['file']));
        header('Content-Length: ' . filesize($_GET['file']));

        if (ob_get_length() !== false) {
            ob_clean();
        }

        flush();
        readfile($_GET['file']);

        exit;
    }

    /**
     * Импортирование БД
     */
    public function importAction()
    {
        // Инициализируем доступ к БД
        $db = Db::getInstance();

        // Файл дампа БД
        $dumpName = addslashes(stream_resolve_include_path($_POST['name']));

        if (!file_exists($dumpName)) {
            echo 'Не найден файл: ' . basename($_POST['name']);
            exit;
        }

        // Получаем массив строк .sql файла из GZIP архива
        $str_list = gzfile($dumpName);

        // Строка с запросами, разделенными ";"
        $query = implode('', $str_list);

        // Выполняем запросы
        if ($db->multi_query($query)) {
            do {
                $db->next_result();
            } while ($db->more_results());
        }

        // Выводим ошибки MySQL, если были
        if ($db->errno) {
            echo $db->error;
        }

        exit;
    }

    /**
     * Загружаем сторонний файл дампа БД
     */
    public function uploadFileAction()
    {
        // Функция для выхода из скрипта
        $exitScript = function ($html, $error) {
            echo json_encode(array('html' => $html, 'error' => $error));
            exit;
        };

        if (!isset($_FILES['file']['name'])) {
            $exitScript('', 'Ошибка: не удалось загрузить файл');
        }

        $time = time();
        $isOverride = false; // перезаписан ли файл

        // Папка сохранения дампов
        $backupPart = stream_resolve_include_path($_GET['bf']);

        // Получаем версию админки
        $versions = new Versions();
        $nowVersions = $versions->getVersions();

        $version = 'v' . $nowVersions['Ideal-CMS'];

        // Имя загружаемого файла
        $srcName = $_FILES['file']['name'];

        // Расширение загружаемого файла (без точки)
        $ext = substr($srcName, strrpos($srcName, '.') + 1);

        // Проверяем соответствие имени загружаемого файла шаблону имени для дампа
        preg_match("/dump_([0-9]{4}\.[0-9]{2}\.[0-9]{2}_[0-9]{2}\.[0-9]{2}\.[0-9]{2}_v[0-9a-z\.]{3,})[\._]/Usmi", $srcName, $m);

        // Имя файла дампа
        $timeName = (!empty($m[1])) ? $m[1] : date('Y.m.d_H.i.s', $time) . '_' . $version;
        $dumpName = 'dump_' . $timeName . '_upload.sql';

        // Полный путь до дампа
        $dumpNameFull = $backupPart . DIRECTORY_SEPARATOR . $dumpName;

        // Полный путь до архива .gz
        $dumpNameGz = $dumpNameFull . '.gz';

        if (!in_array($ext, array('gz', 'zip', 'sql'))) {
            $exitScript('', 'Ошибка: расширение файла должно быть .gz, .sql или .zip');
        }

        if (file_exists($dumpNameGz)) {
            $exitScript('', 'Ошибка: файл с таким именем уже существует');
        }

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dumpNameFull)) {
            $exitScript('', 'Ошибка: не удалось переместить загруженный файл в папку');
        }

        switch ($ext) {
            // Просто переименовываем
            case 'gz':
                rename($dumpNameFull, $dumpNameGz);
                break;
            // Запаковываем .sql в архив GZIP
            case 'sql':
                rename($dumpNameFull, $dumpNameGz);
                $contents = file_get_contents($dumpNameGz);
                $gz = gzopen($dumpNameGz, 'w');
                gzwrite($gz, $contents);
                gzclose($gz);
                break;
            // Перепаковываем из ZIP в GZIP
            case 'zip':
                $archive = new PclZip($dumpNameFull);

                // Получаем список файлов в архиве
                $file_list = $archive->listContent();

                if ($file_list == 0 || count($file_list) != 1) {
                    unlink($dumpNameFull);  // удаляем загруженный файл
                    $exitScript('', 'Ошибка: в архиве должен быть один .sql файл');
                }

                $file = $file_list[0];
                if (!($file['status'] == 'ok' && $file['size'] > 0)) {
                    unlink($dumpNameFull);  // удаляем загруженный файл
                    $exitScript('', 'Ошибка: .sql файл в архиве поврежден или пустой');
                }

                $ext = substr($file['filename'], strrpos($file['filename'], '.') + 1);
                if ($ext != 'sql') {
                    unlink($dumpNameFull);  // удаляем загруженный файл
                    $exitScript('', 'Ошибка: расширение файла должно быть .sql');
                }

                // Меняем обратные слэши на прямые
                $rBackupPart = str_replace("\\", "/", $backupPart);

                // Распаковываем архив в папку с бэкапами
                $files = $archive->extract($rBackupPart);

                if ($files == 0) {
                    $exitScript('', 'Ошибка: не удалось распаковать ZIP-архив');
                }

                // Получаем содержимое распакованного файла
                $sqlName = $backupPart . DIRECTORY_SEPARATOR . $file['filename'];
                $contents = file_get_contents($sqlName);

                // Пакуем в .gz
                $gz = gzopen($dumpNameGz, 'w');
                gzwrite($gz, $contents);
                gzclose($gz);

                unlink($dumpNameFull);  // удаляем загруженный файл
                unlink($sqlName); // удаляем распакованный файл
                break;
        }

        // Формируем строку с новым файлом
        $html = '<tr id="' . $dumpNameGz . '"><td><a href="" onClick="return downloadDump(\'' .
            addslashes($dumpNameGz) . '\')"> ' .
            str_replace('_', ' - ', $timeName) . ' (upload)'
            . '</a></td>'
            . '<td><button class="btn btn-info btn-xs" title="Импортировать" onclick="importDump(\'' .
            addslashes($dumpNameGz) . '\'); return false;">'
            . '<span class="glyphicon glyphicon-upload"></span></button>&nbsp;'

            . '<button class="btn btn-danger btn-xs" title="Удалить" onclick="delDump(\'' .
            addslashes($dumpNameGz) . '\'); return false;">'
            . '<span class="glyphicon glyphicon-remove"></span></button>&nbsp;'

            . '</td></tr>';

        echo json_encode(array('html' => $html, 'error' => false));

        exit;
    }
}
