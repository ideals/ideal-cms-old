<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Part;

use Ideal\Core\Admin\InstallStructureInterface;
use Ideal\Core\Config;
use Ideal\Core\Db;
use Ideal\Structure\Part\Config as PartConfig;

class Install implements InstallStructureInterface
{
    public function run(): void
    {
        $config = Config::getInstance();

        $table = $config->db['prefix'] . 'ideal_structure_part';
        $tableAddon = $config->db['prefix'] . 'ideal_addon_page';

        // Инициализируем доступ к БД
        $db = Db::getInstance();

        // Создание таблицы для страниц
        $db->create($table, PartConfig::$fields);

        // Добавление первого раздела - главной страницы
        $levels = PartConfig::$params['levels'];
        $digits = PartConfig::$params['digits'];
        $count = ($levels - 1) * $digits;

        // Создаём главную страницу
        $db->insert(
            $table,
            [
                'ID' => 1,
                'prev_structure' => '0-1',
                'cid' => str_pad('1', $digits, '0', STR_PAD_LEFT) . str_repeat('0', $count),
                'lvl' => 1,
                'structure' => 'Ideal_Part',
                'template' => 'index.twig',
                'addon' => '[["1", "Ideal_Page", "Текст"]]',
                'name' => 'Главная',
                'url' => '/',
                'date_create' => time(),
                'date_mod' => time(),
                'is_active' => 1
            ]
        );

        // Создаём текст для главной страницы
        $db->insert(
            $tableAddon,
            [
                'ID' => 1,
                'prev_structure' => '1-1',
                'tab_ID' => '1',
                'content' => '<p>Это Главная страница Вашего сайта.</p>',
            ]
        );
    }
}
