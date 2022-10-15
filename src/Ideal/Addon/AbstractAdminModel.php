<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Addon;

use Ideal\Core\Admin;
use Ideal\Core\Db;

/**
 * Абстрактный класс, реализующий основные методы для семейства классов Addon в админской части
 *
 * Аддоны обеспечивают прикрепление к структуре дополнительного содержимого различных типов.
 *
 */
class AbstractAdminModel extends Admin\Model
{
    use TraitModel;

    /**
     * {@inheritdoc}
     */
    public function delete(): bool
    {
        $db = Db::getInstance();
        $db->delete($this->_table)->where('ID=:id', ['id' => $this->pageData['ID']]);
        $db->exec();

        return true;
    }

    public function getPageData(): array
    {
        $this->setPageDataByPrevStructure($this->prevStructure);

        return $this->pageData ?? [];
    }
}
