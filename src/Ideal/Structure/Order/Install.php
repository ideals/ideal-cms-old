<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Order;

use Ideal\Core\Admin\InstallStructureInterface;
use Ideal\Core\Config;
use Ideal\Core\Db;
use Ideal\Structure\Order\Config as OrderConfig;

class Install implements InstallStructureInterface
{
    public function run(): void
    {
        // Инициализируем доступ к БД
        $db = Db::getInstance();

        $config = Config::getInstance();

        $dataListTable = $config->db['prefix'] . 'ideal_structure_datalist';

        $_sql = 'SELECT MAX(pos) as maxPos FROM ' . $dataListTable;
        $max = $db->select($_sql);
        $newPos = (int)$max[0]['maxPos'] + 1;

        // Создание таблицы для справочника
        $db->create($config->db['prefix'] . 'ideal_structure_order', OrderConfig::$fields);

        $db->insert(
            $dataListTable,
            [
                'prev_structure' => '0-3',
                'structure' => 'Ideal_Order',
                'pos' => $newPos,
                'name' => 'Заказы с сайта',
                'url' => 'zakazy-s-sajta',
                'parent_url' => '---',
                'annot' => ''
            ]
        );
    }
}
