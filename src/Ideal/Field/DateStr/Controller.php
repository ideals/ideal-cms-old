<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\DateStr;

use Exception;
use Ideal\Core\Request;
use Ideal\Field\AbstractController;

/**
 * Поле, содержащее дату в формате MySQL Timestamp
 *
 * Пример объявления в конфигурационном файле структуры:
 *     'date_create' => array(
 *         'label' => 'Дата создания',
 *         'sql'   => 'timestamp',
 *         'type'  => 'Ideal_DateStr'
 *     ),
 */
class Controller extends AbstractController
{

    /** {@inheritdoc} */
    protected static $instance;

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function getInputText(): string
    {
        $value = $this->getValue();
        $date = empty($value) ? '' : date('d.m.Y H:i:s', strtotime($value));
        $htmlName = $this->htmlName;
        return <<<HTML
<div id="picker_$htmlName" class="input-group date">
    <span class="input-group-addon">
        <span class="glyphicon glyphicon-calendar" ></span>
    </span>
    <input type="datetime-local" class="form-control" name="$htmlName" value="$date" >
</div>
HTML;
    }

    /**
     * {@inheritdoc}
     */
    public function getValueForList(array $values, string $fieldName): string
    {
        return date('d.m.Y &\nb\sp; H:i', strtotime($values[$fieldName]));
    }

    /**
     * {@inheritdoc}
     * @noinspection MultipleReturnStatementsInspection
     */
    public function pickupNewValue(): string
    {
        $request = new Request();

        $fieldName = $this->htmlName;
        $newValue = $request->$fieldName;

        if (empty($newValue)) {
            return '0';
        }

        $dateTime = date_create_from_format('d.m.Y H:i:s', $newValue);
        if ($dateTime === false) {
            // Ошибка в формате введённой даты
            return '';
        }

        $this->newValue = $dateTime->format('Y-m-d H:i:s');
        return $this->newValue;
    }
}
