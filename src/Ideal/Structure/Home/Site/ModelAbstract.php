<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Home\Site;

use Ideal\Core\Db;
use Ideal\Structure\Part;

class ModelAbstract extends Part\Site\Model
{
    public function detectPageByUrl(array $path, array $url)
    {
        $db = Db::getInstance();

        $_sql = "SELECT * FROM $this->_table WHERE BINARY url=:url LIMIT 1";

        $list = $db->select($_sql, ['url' => $url[0]]); // получение всех страниц, соответствующих частям url

        // Страницу не нашли, возвращаем 404
        if (!isset($list[0]['cid'])) {
            $this->path = $path;
            $this->is404 = true;
            return $this;
        }

        $this->path = array_merge($path, $list);

        return $this;
    }
}
