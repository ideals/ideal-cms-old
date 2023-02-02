<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Medium\UserGroupList;

use Ideal\Core\Config;
use Ideal\Core\Db;
use Ideal\Medium\AbstractModel;

/**
 * Медиум для получения списка групп пользователей
 */
class Model extends AbstractModel
{
    /**
     * {@inheritdoc}
     */
    public function getList(): array
    {
        $list = [0 => '---'];
        $db = Db::getInstance();
        $config = Config::getInstance();
        $table = $config->db['prefix'] . 'ideal_structure_usergroup';
        /** @noinspection SqlResolve */
        $sql = 'SELECT ID, name FROM ' . $table . ' ORDER BY name';
        $arr = $db->select($sql);
        foreach ($arr as $item) {
            $list[$item['ID']] = $item['name'];
        }
        return $list;
    }
}
