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

        $this->copyFolder(
            $this->vendorDir . '/ideals/idealcms-old/front/css',
            $wwwDir . '/css/' . $config->cmsFolder
        );

        $this->copyFolder(
            $this->vendorDir . '/idealcms/bootstrap-multiselect/dist',
            $wwwDir . '/js/' . $config->cmsFolder . '/bootstrap-multiselect'
        );

        $this->copyFolder(
            $this->vendorDir . '/idealcms/ckeditor/dist',
            $wwwDir . '/js/' . $config->cmsFolder . '/ckeditor'
        );

        $this->copyFolder(
            $this->vendorDir . '/idealcms/ckfinder/dist',
            $wwwDir . '/js/' . $config->cmsFolder . '/ckfinder'
        );

        $this->copyFolder(
            $this->vendorDir . '/components/jquery',
            $wwwDir . '/js/' . $config->cmsFolder . '/jquery'
        );

        $this->copyFolder(
            $this->vendorDir . '/components/jqueryui',
            $wwwDir . '/js/' . $config->cmsFolder . '/jqueryui'
        );

        $this->copyFolder(
            $this->vendorDir . '/twitter/bootstrap/dist/',
            $wwwDir . '/js/' . $config->cmsFolder . '/bootstrap'
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
