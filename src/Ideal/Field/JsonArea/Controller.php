<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\JsonArea;

use Ideal\Field\AbstractController;
use JsonException;

/**
 * Отображение редактирования поля в админке в виде textarea.
 * В базе данные хранятся в виде json-представления
 *
 * Пример объявления в конфигурационном файле структуры:
 *     'phones' => array(
 *         'label' => 'Телефоны',
 *         'sql'   => 'text',
 *         'type'  => 'Ideal_JsonArea'
 *     ),
 */
class Controller extends AbstractController
{

    /** {@inheritdoc} */
    protected static $instance;

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function getInputText(): string
    {
        $value = $this->getValue();
        return
            '<textarea class="form-control" name="' . $this->htmlName
            . '" id="' . $this->htmlName
            . '">' . $value . '</textarea>';
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function getValue(): string
    {
        $value = json_decode(parent::getValue(), true, 512, JSON_THROW_ON_ERROR);
        if (!empty($value) && is_array($value)) {
            $value = implode("\n", $value);
        }
        return $value;
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function pickupNewValue(): string
    {
        $value = parent::pickupNewValue();
        if (!empty($value)) {
            $valueArr = array_filter(preg_split('/\s/', $value));
            $value = json_encode(array_values($valueArr), JSON_THROW_ON_ERROR);
        }
        return $value;
    }
}
