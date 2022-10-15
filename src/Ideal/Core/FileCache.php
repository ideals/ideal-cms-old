<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core;

use FilesystemIterator;
use Ideal\Structure\Service\SiteData\ConfigPhp;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Класс обеспечивает работу с файловым кэшем
 *
 * Пример инициализации процесса сохранения страницы в кэш:
 *   FileCache::saveCache('HTML-содержимое', 'адрес страницы');
 */
class FileCache
{

    /**
     * Сохраняет содержимое в файл кэша
     *
     * @param string $content Контент подлежащий сохранению
     * @param string $uri Путь, используется в построении иерархии директорий и имени самого файла.
     * @param int $modifyTime Timestamp представление даты последнего изменения информации о странице
     */
    public static function saveCache(string $content, string $uri, int $modifyTime): void
    {
        $config = Config::getInstance();
        $configCache = $config->cache;

        // Получаем чистый $uri без GET параметров
        [$uri] = explode('?', $uri, 2);

        // Удаляем первый слэш, для использования пути в проверке на исключения
        $stringToCheck = preg_replace('/\//', '', $uri, 1);

        $uriArray = self::getModifyUri($uri);

        $excludeCacheFileValue = explode("\n", $configCache['excludeFileCache']);

        $excludeCacheFileValue = array_filter($excludeCacheFileValue);

        $exclude = false;
        foreach ($excludeCacheFileValue as $pattern) {
            if (preg_match($pattern, $stringToCheck)) {
                $exclude = true;
            }
        }

        // Проверяем наличие рассматриваемого пути в исключениях
        if (!$exclude) {
            // Путь до общего каталога закэшированных страниц
            $cacheDir = DOCUMENT_ROOT . $config->cms['tmpFolder'] . '/cache/fileCache';

            self::checkDir($cacheDir);

            $fileName = array_pop($uriArray);
            if (!empty($uriArray)) {
                $dirPath = $cacheDir . '/' . implode('/', $uriArray);
            } else {
                $dirPath = $cacheDir;
            }

            self::checkDir($dirPath);

            // Записываем файл кэша
            if (file_put_contents($dirPath . '/' . $fileName, $content) !== false) {
                touch($dirPath . '/' . $fileName, $modifyTime);
            }
        }
    }

    /**
     * Очищает весь файловый кэш
     */
    public static function clearFileCache(): void
    {
        $config = Config::getInstance();
        $ds = DIRECTORY_SEPARATOR;
        $dir = DOCUMENT_ROOT . $config->cms['tmpFolder'] . $ds . 'cache' . $ds . 'fileCache';
        if (is_dir($dir)) {
            $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Добавляет значение исключения файлового кэша
     *
     * @param string $string Адрес для исключения из кэширования
     *
     * @return bool Флаг, отражающий успешность добавления адреса в исключения
     * @throws JsonException
     */
    public static function addExcludeFileCache(string $string): bool
    {
        $config = Config::getInstance();

        // Проверяем на существование файл кэша, при надобности удаляем
        preg_match('/^\/(.*)\/[imsxADSUXJu]{0,11}$/', $string, $cacheFiles);

        // Добавляем путь до общей папки хранения файлового кэширования
        if (!empty($cacheFiles[1])) {
            $cacheFiles[1] = $config->cms['tmpFolder'] . '/cache/fileCache/' . $cacheFiles[1];
            $cacheFiles[1] = ltrim($cacheFiles[1], '/');
        }
        $cacheFiles = glob(stripcslashes($cacheFiles[1]));
        if (!empty($cacheFiles)) {
            foreach ($cacheFiles as $cacheFile) {
                self::delCacheFileDir('/' . $cacheFile);
            }
        }

        $config = Config::getInstance();
        $configSD = new ConfigPhp();
        $file = DOCUMENT_ROOT . '/' . $config->cmsFolder . '/site_data.php';
        $configSD->loadFile($file);
        $params = $configSD->getParams();
        $excludeCacheFileValue = explode("\n", $params['cache']['arr']['excludeFileCache']['value']);

        $res = true;
        if (!in_array($string, $excludeCacheFileValue, true)) {
            $excludeCacheFileValue[] = $string;
            $params['cache']['arr']['excludeFileCache']['value'] = implode("\n", $excludeCacheFileValue);
            $configSD->setParams($params);
            $file = DOCUMENT_ROOT . '/' . $config->cmsFolder . '/site_data.php';
            $res = (bool)$configSD->saveFile($file);
        }

        return $res;
    }

    public static function getModifyUri(&$uri): array
    {
        $config = Config::getInstance();
        $configCache = $config->cache;

        $uriArray = array_values(array_filter(explode('/', $uri)));

        $pageName = end($uriArray);
        reset($uriArray);

        // Если это главная страница или каталог
        if (!$pageName || !preg_match('/\..*$/', $pageName)) {
            if (!preg_match('/\/$/', $uri)) {
                $uri .= '/';
            }
            $uri .= $configCache['indexFile'];
            $uriArray[] = $configCache['indexFile'];
        }

        return $uriArray;
    }

    /**
     * Проверяет на существование нужную директорию, если таковая отсутствует, то создаёт её
     *
     * @param string $path Путь к папке
     */
    private static function checkDir(string $path): void
    {
        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
        }
    }

    /**
     * Удаляет файл кэша и директории его нахождения, если они пустые
     *
     * @param string $path Путь до удаляемого файла
     * @return bool
     */
    public static function delCacheFileDir(string $path): bool
    {

        self::getModifyUri($path);

        $res = false;

        // Удаляем сам файл
        if (file_exists(DOCUMENT_ROOT . $path)) {
            unlink(DOCUMENT_ROOT . $path);

            // Последовательная проверка каждого каталога из всей иерархии на возможность удаления
            $dirArray = array_values(array_filter(explode('/', $path)));
            array_pop($dirArray);
            if (!empty($dirArray)) {
                // Получаем массив с полными путями до каждого каталога в иерархии
                $implodeDirArrayElement = [];
                $count = count($dirArray);
                for ($i = 0; $i < $count; $i++) {
                    // TODO продумать вариант получения пути по красивее
                    $dirPath = implode('/', explode('/', implode('/', $dirArray), 0 - $i));
                    $implodeDirArrayElement[] = DOCUMENT_ROOT . '/' . $dirPath;
                }

                // Попытка удаления каждого каталога из иерархии
                foreach ($implodeDirArrayElement as $dirPath) {
                    if (count(glob($dirPath . '/*'))) {
                        break;
                    }
                    rmdir($dirPath);
                }
            }
            $res = true;
        }

        return $res;
    }
}
