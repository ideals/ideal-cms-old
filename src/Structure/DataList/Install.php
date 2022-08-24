<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\DataList;

use Ideal\Core\Admin\InstallStructureInterface;
use Ideal\Core\Config;
use Ideal\Core\Db;
use Ideal\Structure\DataList\Config as DataListConfig;

class Install implements InstallStructureInterface
{
    public function run(): void
    {
        $db = Db::getInstance();
        $config = Config::getInstance();

        // Создание таблицы для справочника
        $db->create($config->db['prefix'] . 'ideal_structure_datalist', DataListConfig::$fields);
    }
}
