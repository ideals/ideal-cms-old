<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\News;

use Ideal\Medium;

// Новости
class Config
{
    public static array $params = [
        'in_structures' => ['Ideal_Part'], // в каких структурах можно создавать эту структуру
        'elements_cms' => 10, // количество элементов в списке в CMS
        'elements_site' => 15, // количество элементов в списке на сайте
        'field_name' => '', // поле для входа в список потомков
        'field_sort' => 'date_create DESC', // поле, по которому проводится сортировка в CMS по умолчанию
        'field_list' => ['name', 'is_active', 'date_create'],
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
        'tag' => [
            'label' => 'Теги',
            'sql' => '',
            'type' => 'Ideal_SelectMulti',
            'medium' => Medium\TagList\Model::class,
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
        'img' => [
            'label' => 'Картинка',
            'sql' => 'varchar(255)',
            'type' => 'Ideal_Image',
        ],
        'annot' => [
            'label' => 'Аннотация',
            'sql' => 'text',
            'type' => 'Ideal_Area',
        ],
        'date_create' => [
            'label' => 'Дата создания',
            'sql' => 'int(11) not null',
            'type' => 'Ideal_DateSet',
        ],
        'content' => [
            'label' => 'Сообщение',
            'sql' => 'text',
            'type' => 'Ideal_RichEdit',
        ],
        'is_active' => [
            'label' => 'Отображать на сайте',
            'sql' => 'bool',
            'type' => 'Ideal_Checkbox',
        ],
    ];
}
