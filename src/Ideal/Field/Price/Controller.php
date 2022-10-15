<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\Price;

use Exception;
use Ideal\Core\Request;
use Ideal\Field\AbstractController;

/**
 * Class Controller
 */
class Controller extends AbstractController
{
    protected static $instance;

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function getInputText(): string
    {
        $value = str_replace(',', '.', htmlspecialchars($this->getValue()));
        return '<input type="number" step="0.01" class="form-control '
            . '" name="' . $this->htmlName
            . '" id="' . $this->htmlName
            . '" value="' . $value . '">';
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(): string
    {
        return (int)parent::getValue() / 100;
    }

    /**
     * {@inheritdoc}
     */
    public function getValueForList(array $values, string $fieldName): string
    {
        return number_format($values[$fieldName] / 100, 2, ',', ' ');
    }

    /**
     * {@inheritdoc}
     */
    public function pickupNewValue(): string
    {
        $request = new Request();
        $fieldName = $this->groupName . '_' . $this->name;
        $this->newValue = $request->$fieldName * 100;
        return $this->newValue;
    }
}
