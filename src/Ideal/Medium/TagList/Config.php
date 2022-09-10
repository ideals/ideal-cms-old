<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Medium\TagList;

class Config
{
    public static array $params = [
        'has_table' => true,
    ];

    public static array $fields = [
        'part_id' => [
            'label' => 'Идентификатор страницы',
            'sql'   => 'int(11)',
        ],
        'tag_id' => [
            'label' => 'Идентификатор тега',
            'sql'   => 'int(11)',
        ],
        'structure_id' => [
            'label' => 'Структура, элементу которой присвоен тег',
            'sql'   => 'char(15)',
        ],
    ];
}
