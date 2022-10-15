<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Service\Error404;

use Ideal\Core\Config;
use Ideal\Structure\Service\SiteData\ConfigPhp;
use JsonException;

class Action
{
    /**
     * @throws JsonException
     */
    public function render(): string
    {
        $result = '<form action="" method=post enctype="multipart/form-data">';

        $config = Config::getInstance();
        $file = new ConfigPhp();

        $file->loadFile($config->rootDir . '/config/known404.php');

        if (isset($_POST['edit'])) {
            $file->changeAndSave($config->rootDir . '/config/known404.php');
        }

        $result .= $file->showEdit()
            . '<br/>'
            . '<input type="submit" class="btn btn-info" name="edit" value="Сохранить настройки"/>'
            . '</form>';

        return $result;
    }
}
