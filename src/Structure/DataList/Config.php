<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\DataList;

use Ideal\Medium;

// Новости
class Config
{
    public static array $params = [
        'in_structures' => [], // в каких структурах можно создавать эту структуру
        'elements_cms' => 10, // количество элементов в списке в CMS
        'elements_site' => 15, // количество элементов в списке на сайте
        'field_name' => 'name', // поле для входа в список потомков
        'field_sort' => 'pos DESC', // поле, по которому проводится сортировка в CMS по умолчанию
        'field_list' => ['name'],
    ];

    public static array $fields = [
        'ID' => [
            'label' => 'Идентификатор',
            'sql' => 'int(4) unsigned not null auto_increment primary key',
            'type' => 'Ideal_Hidden',
        ],
        'prev_structure' => [
            'label' => 'ID родительских структур',
            'sql' => 'char(15)',
            'type' => 'Ideal_Hidden',
        ],
        'structure' => [
            'label' => 'Тип раздела',
            'sql' => 'varchar(30) not null',
            'type' => 'Ideal_Select',
            'medium' => Medium\StructureList\Model::class,
        ],
        'pos' => [
            'label' => 'Сортировка',
            'sql' => 'int(4) unsigned not null',
            'type' => 'Ideal_Text',
        ],
        'name' => [
            'label' => 'Заголовок',
            'sql' => 'varchar(255) not null',
            'type' => 'Ideal_Text',
        ],
        'url' => [
            'label' => 'URL',
            'sql' => 'varchar(255) not null',
            'type' => 'Ideal_UrlAuto',
        ],
        'parent_url' => [
            'label' => 'URL списка элементов',
            'sql' => 'varchar(255) not null',
            'type' => 'Ideal_Text',
        ],
        'annot' => [
            'label' => 'Аннотация',
            'sql' => 'text',
            'type' => 'Ideal_Area',
        ],
    ];
}
