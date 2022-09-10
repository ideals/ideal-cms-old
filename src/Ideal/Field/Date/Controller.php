<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\Date;

use Ideal\Core\Request;
use Ideal\Field\AbstractController;

/**
 * Поле, содержащее дату в формате MySQL DataTime
 *
 * Для редактирования подключается jQuery-плагин datetimepicker.js, который использует библиотеку Moment.
 * Пример объявления в конфигурационном файле структуры:
 *     'date_create' => array(
 *         'label' => 'Дата создания',
 *         'sql'   => 'int(11) not null',
 *         'type'  => 'Ideal_DateSet'
 *     ),
 */
class Controller extends AbstractController
{

    /** {@inheritdoc} */
    protected static $instance;

    /**
     * {@inheritdoc}
     */
    public function getInputText()
    {
        $value = $this->getValue();
        $date = empty($value) ? '' : date('Y-m-d\TH:i', $value);
        $htmlName = $this->htmlName;
        $html = <<<HTML

<div id="picker_{$htmlName}" class="input-group date">
    <span class="input-group-addon">
        <span class="glyphicon glyphicon-calendar" ></span>
    </span>
    <input type="datetime-local" class="form-control" name="{$htmlName}" value="{$date}" >
</div>
HTML;

        return $html;
    }

    /**
     * {@inheritdoc}
     */
    public function getValueForList($values, $fieldName)
    {
        return date('d.m.Y &\nb\sp; H:i', $values[$fieldName]);
    }

    /**
     * {@inheritdoc}
     */
    public function pickupNewValue()
    {
        $request = new Request();

        $fieldName = $this->htmlName;
        $newValue = $request->$fieldName;

        if (empty($newValue)) {
            return '0';
        }

        $dateTime = date_create_from_format('Y-m-d\TH:i', $newValue);
        if ($dateTime === false) {
            // Ошибка в формате введённой даты
            return '';
        }

        $this->newValue = $dateTime->getTimestamp();
        return $this->newValue;
    }
}
