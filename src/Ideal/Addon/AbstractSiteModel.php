<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Addon;

use Ideal\Core\Site\Model;

/**
 * Абстрактный класс, реализующий основные методы для семейства классов Addon во внешней части сайта
 *
 * Аддоны обеспечивают прикрепление к структуре дополнительного содержимого различных типов.
 */
class AbstractSiteModel extends Model
{
    use TraitModel;

    /**
     * {@inheritdoc}
     */
    public function detectPageByUrl(array $path, array $url)
    {
    }
}
