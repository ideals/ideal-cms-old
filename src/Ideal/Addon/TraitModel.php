<?php

namespace Ideal\Addon;

use Ideal\Core\Db;

trait TraitModel
{
    public function setPageDataByPrevStructure($prevStructure): void
    {
        $db = Db::getInstance();

        // Получаем идентификатор таба из группы
        [, $tabID] = explode('-', $this->fieldsGroup, 2);
        $_sql = "SELECT * FROM $this->_table WHERE prev_structure=:ps AND tab_ID=:tid";
        $pageData = $db->select($_sql, ['ps' => $prevStructure, 'tid' => $tabID]);
        if (isset($pageData[0]['ID'])) {
            // TODO сделать обработку ошибки, когда по prevStructure ничего не нашлось
            $this->setPageData($pageData[0]);
        }
    }
}
