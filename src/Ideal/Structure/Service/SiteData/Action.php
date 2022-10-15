<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Service\SiteData;

use Ideal\Core\Config;
use Ideal\Structure\Service\Cache\Model as CacheModel;
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

        $file->loadFile($config->rootDir . '/config/site.php');

        if (isset($_POST['edit'])) {
            $fileCache = new CacheModel($file);
            $response = $fileCache->checkSettings();
            $response['text'] = empty($response['text']) ? 'Настройки сохранены' : $response['text'];
            $result .= $file->changeAndSave(
                $config->rootDir . '/config/site.php',
                $response['res'],
                $response['class'],
                $response['text']
            );
        }
        $result .= $file->showEdit()
            . '<br/><input type="submit" class="btn btn-info" name="edit" value="Сохранить настройки"/></form>';

        return $result;
    }
}

