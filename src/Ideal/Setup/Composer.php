<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2022 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Setup;

use Composer\Script\Event;
use JsonException;

/**
 * https://getcomposer.org/doc/articles/scripts.md
 */
class Composer
{
    /**
     * Запускается после каждого composer update
     *
     * @param Event $event
     *
     * @return void
     *
     * @noinspection PhpUnused
     */
    public static function postUpdate(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        require $vendorDir . '/autoload.php';

        $installer = new PostUpdate($vendorDir);
        $installer->run();
    }


    /**
     * Запускается однократно - после установки проекта
     *
     * @param Event $event
     *
     * @return void
     *
     * @noinspection PhpUnused
     * @throws JsonException
     */
    public static function postCreateProject(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        require $vendorDir . '/autoload.php';

        $installer = new PostCreateProject($vendorDir, $event);
        $installer->run();
    }
}
