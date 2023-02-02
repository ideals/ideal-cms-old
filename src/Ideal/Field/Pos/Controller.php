<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\Pos;

use Exception;
use Ideal\Core\Request;
use Ideal\Field\AbstractController;

/**
 * Поле для сортировки элементов по порядку значений
 *
 * Пример объявления в конфигурационном файле структуры:
 *     'pos' => array(
 *         'label' => '№',
 *         'sql'   => 'int not null',
 *         'type'  => 'Ideal_Pos'
 *     ),
 */
class Controller extends AbstractController
{

    /** @inheritdoc */
    protected static $instance;

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function getInputText(): string
    {
        $value = $this->getValue();

        return '<input type="text" class="form-control" name="' . $this->htmlName
            . '" id="' . $this->htmlName
            . '" value="' . $value . '">';
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function pickupNewValue(): string
    {
        $request = new Request();
        $fieldName = $this->groupName . '_' . $this->name;
        $this->newValue = $request->$fieldName;
        $model = $this->model;

        $newPos = $this->newValue;
        $pageData = $this->model->getPageData();
        $oldPos = $pageData['pos'] ?? 0;

        // Если был указан и не изменился, то оставляем как есть
        // Если был указан и изменился, перенумеруем список
        if ($this->newValue === '') {
            // Если pos не был указан, надо поставить максимальный
            $posModel = new Model();
            $this->newValue = $posModel->getNewPos($model);
        } elseif ($oldPos !== $newPos) {
            $posModel = new Model();
            $this->sqlAdd = $posModel->movePos($oldPos, $newPos, $model->getPrevStructure());
            $this->newValue = $oldPos; // Возвращаем старое значение, т.к. все перестановки идут в movePos
        }

        return $this->newValue;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function showEdit(): string
    {
        $this->htmlName = $this->groupName . '_' . $this->name;
        $value = $this->getValue();

        if ($value === '') {
            $html = '<input type="hidden" id="' . $this->htmlName
                . '" name="' . $this->htmlName
                . '" value="' . $value . '">';
        } else {
            $html = parent::showEdit();
        }
        return $html;
    }
}
