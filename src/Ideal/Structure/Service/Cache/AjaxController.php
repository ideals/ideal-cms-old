<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Service\Cache;

use Ideal\Addon\SiteMap;
use Ideal\Core\Admin\AjaxController as CoreAjaxController;
use Ideal\Core\Config;
use Ideal\Core\FileCache;
use Ideal\Core\Memcache;
use Ideal\Core\View;
use JsonException;

/**
 * Сброс всего кэширования
 *
 */
class AjaxController extends CoreAjaxController
{

    /**
     * Действие срабатывающее при нажатии на кнопку "Очистить кэш"
     *
     * @throws JsonException
     */
    public function clearCacheAction()
    {
        $config = Config::getInstance();
        $configCache = $config->cache;

        // Очищаем файловый кэш
        if (isset($configCache['fileCache']) && $configCache['fileCache']) {
            FileCache::clearFileCache();
        }

        // Очищаем Memcache, только если он включён в настройках
        if ($config->cache['memcache']) {
            $memcache = Memcache::getInstance();
            $memcache->flush();
        }

        // Очищаем twig кэш
        View::clearTwigCache();

        // Удаляем сжатый css
        if (file_exists($config->publicDir . '/css/all.min.css')) {
            unlink($config->publicDir . '/css/all.min.css');
        }

        // Удаляем сжатый js
        if (file_exists($config->publicDir . '/js/all.min.js')) {
            unlink($config->publicDir . '/js/all.min.js');
        }

        print json_encode(['text' => 'ok'], JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Действие срабатывающее при нажатии на кнопку "Очистить кэш"
     *
     * @throws JsonException
     */
    public function clearCacheFilesAction()
    {
        $config = Config::getInstance();
        $delPages = [];
        $pageList = new SiteMap\SiteModel('0-1');
        $pages = $pageList->getList();
        foreach ($pages as $page) {
            $path = $config->rootDir . '/' . $config->cms['tmpFolder'] . '/cache/fileCache' . $page['link'];
            if (FileCache::delCacheFileDir($path)) {
                $delPages[] = $page['link'];
            }
        }
        $delPages = implode('<br />', $delPages);

        print json_encode(['text' => $delPages], JSON_THROW_ON_ERROR);
        exit;
    }
}
