<?php

namespace Ideal\Setup;

use Composer\Script\Event;

/**
 * https://getcomposer.org/doc/articles/scripts.md
 */
class Update
{
    public static function postUpdate(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');

        $installer = new Installer();
        $installer->run($vendorDir);

        require $vendorDir . '/autoload.php';
    }


    public static function postCreateProject(Event $event): void
    {

    }
}
