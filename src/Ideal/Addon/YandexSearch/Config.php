<?php
namespace Ideal\Addon\YandexSearch;

// ЯндексПоиск
class Config
{
    public static array $params = [
        'name' => 'ЯндексПоиск',
    ];

    public static array $fields = [
        'ID' => [
            'label' => 'Идентификатор',
            'sql' => 'int(8) unsigned not null auto_increment primary key',
            'type' => 'Ideal_Hidden',
        ],
        'prev_structure' => [
            'label' => 'ID родительских структур',
            'sql' => 'char(15)',
            'type' => 'Ideal_Hidden',
        ],
        'tab_ID' => [
            'label' => 'ID таба аддона',
            'sql' => 'int not null default 0',
            'type' => 'Ideal_Hidden',
        ],
        'yandexLogin' => [
            'label' => 'Яндекс логин',
            'sql' => 'varchar(255)',
            'type' => 'Ideal_Text',
        ],
        'yandexKey' => [
            'label' => 'Яндекс ключ',
            'sql' => 'varchar(255)',
            'type' => 'Ideal_Text',
        ],
        'elements_site' => [
            'label' => 'Количество элементов в выдаче',
            'sql' => 'int(8)',
            'type' => 'Ideal_Integer',
        ],
        'content' => [
            'label' => 'Текст',
            'sql' => 'mediumtext',
            'type' => 'Ideal_RichEdit',
        ],
    ];
}
