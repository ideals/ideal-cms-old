<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Order;

// Заявки
class Config
{
    public static array $params = [
        'in_structures' => ['Ideal_DataList'], // в каких структурах можно создавать эту структуру
        'elements_cms' => 10, // количество элементов в списке в CMS
        'elements_site' => 15, // количество элементов в списке на сайте
        'field_name' => '', // поле для входа в список потомков
        'field_sort' => 'date_create DESC', // поле, по которому проводится сортировка в CMS по умолчанию
        'field_list' => ['date_create', 'name', 'email', 'price', 'referer', 'order_type'],
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
        'date_create' => [
            'label' => 'Дата создания',
            'sql' => 'int(11) not null',
            'type' => 'Ideal_DateSet',
        ],
        'name' => [
            'label' => 'Имя',
            'sql' => 'varchar(255) not null',
            'type' => 'Ideal_Text',
        ],
        'email' => [
            'label' => 'Email',
            'sql' => 'varchar(255)',
            'type' => 'Ideal_Text',
        ],
        'phone' => [
            'label' => 'Телефон',
            'sql' => 'varchar(100)',
            'type' => 'Ideal_Text',
        ],
        'client_id' => [
            'label' => 'Google ClientID',
            'sql' => 'varchar(100)',
            'type' => 'Ideal_Text',
        ],
        'price' => [
            'label' => 'Сумма заказа',
            'sql' => 'int',
            'type' => 'Ideal_Price',
        ],
        'referer' => [
            'label' => 'Источник перехода',
            'sql' => 'varchar(255) not null',
            'type' => 'Ideal_Referer',
        ],
        'content' => [
            'tab' => 'Заказ',
            'label' => 'Заказ',
            'sql' => 'mediumtext',
            'type' => 'Ideal_RichEdit',
        ],
        'order_type' => [
            'label' => 'Тип заказа',
            'sql' => 'varchar(255) not null',
            'type' => 'Ideal_Text',
        ],
    ];
}
