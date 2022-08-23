<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2022 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Setup;

use Composer\Script\Event;
use Ideal\Core\ConfigEdit;
use JsonException;
use RuntimeException;

class PostCreateProject
{
    private ConfigEdit $cmsConfig;

    private string $rootDir;

    private string $vendorDir;

    private Event $event;

    /**
     * @param string $vendorDir
     * @param Event $event
     *
     * @throws JsonException
     */
    public function __construct(string $vendorDir, Event $event)
    {
        $this->vendorDir = $vendorDir;
        $this->event = $event;

        $this->rootDir = stream_resolve_include_path($vendorDir . '/..');

        $this->cmsConfig = new ConfigEdit();

        $file = $this->vendorDir . '/ideals/idealcms-old/config/cms.php';

        if (!$this->cmsConfig->loadFile($file)) {
            throw new \http\Exception\RuntimeException('Отсутствует файл ' . $file);
        }
    }

    /**
     * @return void
     *
     * @throws JsonException
     */
    public function run(): void
    {
        $io = $this->event->getIO();

        $domain = trim($io->ask('Domain name [example.com]: ', 'example.com'));

        $folder = trim(trim($io->ask('Public folder [public_html]: ', 'public_html')), '/');
        $wwwDir = $this->rootDir . '/' . $folder;

        if (!file_exists($wwwDir) && !mkdir($wwwDir) && !is_dir($wwwDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $wwwDir));
        }

        $adminFolder = trim($io->ask('Admin folder [adminka]: ', 'adminka'));

        $params = $this->cmsConfig->getParams();
        $params['cms']['array']['publicFolder']['value'] = $folder;
        $params['domain']['value'] = $domain;
        $params['cmsFolder']['value'] = $adminFolder;
        $this->cmsConfig->setParams($params);

        $configFolder = $this->rootDir . '/config';
        if (!file_exists($configFolder) && !mkdir($configFolder) && !is_dir($configFolder)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $configFolder));
        }

        $this->cmsConfig->saveFile($configFolder . '/cms.php');

        $update = new PostUpdate($this->vendorDir, $this->event);
        $update->run();
    }
}
