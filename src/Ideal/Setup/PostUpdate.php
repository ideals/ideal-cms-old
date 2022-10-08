<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2022 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Setup;

use Composer\Script\Event;
use Ideal\Core\Config;
use RuntimeException;

class PostUpdate
{
    private string $rootDir;

    private string $vendorDir;

    public function __construct(string $vendorDir, Event $event)
    {
        $this->vendorDir = $vendorDir;
        $this->rootDir = stream_resolve_include_path($vendorDir . '/..');
    }

    public function run(): void
    {
        if (!file_exists($this->rootDir . '/config/cms.php')) {
            // Это первоначальная установка, пока не копируем файлы
            return;
        }

        $config = Config::getInstance();
        $config->loadSettings($this->rootDir);

        $wwwDir = $this->rootDir . '/' . $config->cms['publicFolder'];
        $adminDir = $wwwDir . '/' . $config->cmsFolder;

        $this->copyFolder(
            $config->cmsDir . '/front/admin/css',
            $adminDir . '/css/'
        );

        $this->copyFolder(
            $config->cmsDir . '/front/admin/js',
            $adminDir . '/js/'
        );

        $this->copyFolder(
            $this->vendorDir . '/idealcms/bootstrap-multiselect/dist',
            $adminDir . '/js/' . '/bootstrap-multiselect'
        );

        $this->copyFolder(
            $this->vendorDir . '/idealcms/ckeditor/dist',
            $adminDir . '/js/' . '/ckeditor'
        );

        $this->copyFolder(
            $this->vendorDir . '/idealcms/ckfinder/dist',
            $adminDir . '/js/' . '/ckfinder'
        );

        $this->copyFolder(
            $this->vendorDir . '/components/jquery',
            $adminDir . '/js/' . '/jquery'
        );

        $this->copyFolder(
            $this->vendorDir . '/components/jqueryui',
            $adminDir . '/js/' . '/jqueryui'
        );

        $this->copyFolder(
            $this->vendorDir . '/twitter/bootstrap/dist/',
            $adminDir . '/js/' . '/bootstrap'
        );
    }

    /**
     * Рекурсивно копирует папку из $from в $to (пути абсолютные)
     *
     * @param string $from Источник копирования
     * @param string $to Цель копирования
     *
     * @return void
     */
    protected function copyFolder(string $from, string $to): void
    {
        $dir = opendir($from);
        if (!file_exists($to) && !mkdir($to, 0777, true) && !is_dir($to)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $to));
        }
        while (($file = readdir($dir)) !== false) {
            if (($file !== '.') && ($file !== '..')) {
                if (is_dir($from . '/' . $file)) {
                    $this->copyFolder($from . '/' . $file, $to . '/' . $file);
                } else {
                    copy($from . '/' . $file, $to . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
}
