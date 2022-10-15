<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2014 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\Addon;

use Ideal\Core\Util;
use Ideal\Field\AbstractController;
use JsonException;

/**
 * Подключение addon'ов к элементу структуры сайта
 *
 * Отображается в виде скрытого поля ввода <input type="hidden" />. В нём содержится список подключённых
 * к этому элементу addon'ов, кнопки редактирования списка подключённых addon'ов (рядом со списком вкладок)
 * и окна редактирования списка addon'ов (которое появляется при нажатии на эту кнопку).
 *
 * Список addon'ов хранится в json-массиве.
 *
 * ВАЖНО! У каждого элемента структуры может быть только одно поле Ideal_Addon.
 *
 * Пример объявления в конфигурационном файле структуры:
 *     'addon' => array(
 *         'label' => "Список подключённых аддонов",
 *         'sql'   => 'text',
 *         'type'  => 'Ideal_Addon'
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
    public function showEdit(): string
    {
        $this->htmlName = $this->groupName . '_' . $this->name;
        return $this->getInputText();
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function getInputText(): string
    {
        $value = $this->getValue();

        $addonModel = new Model();
        $addonModel->setModel($this->model, $this->name, $this->groupName);

        // Формируем содержание выпадающего окна редактирования вкладок
        $editHtml = '<div id="addonsList"></div>'
            . $addonModel->getAvailableAddonsList()
            . '<button class="btn btn-link" onclick="$(\'#tabsModal\').toggle()">Закрыть</button>';

        $editHtml = strtr($editHtml, ["\n" => '']);
        $editHtml = addcslashes($editHtml, "'");

        // Получаем список доступных аддонов
        $availableAddons = htmlspecialchars(
            json_encode($this->field['available'], JSON_THROW_ON_ERROR)
        );

        $tabs = $addonModel->getTabs($value);

        $valueHtml = htmlspecialchars($value);

        return <<<HTML
            <input type="hidden" id="$this->htmlName" name="$this->htmlName" value="$valueHtml">
            <input type="hidden" id="available_addons" name="available_addons" value="$availableAddons">
            <script type="text/javascript">
            function getAddonFieldName() {
                return "$this->htmlName";
            }
            $(document).ready(function() {
                // Добавляем контент во всплывающее окно редактирования вкладок
                $('#tabsModal').html('$editHtml');

                // Включаем кнопку редактирования вкладок
                $('#modalTabsEdit').removeClass('hide');

                // Добавляем вкладки к списку вкладок
                $('#tabs').append('{$tabs['names']}');

                // Добавляем собственно само содержимое вкладок
                $('#tabs-content').append('{$tabs['contents']}');
            });
            </script>
            <script type="text/javascript" src="?mode=ajax&action=script&controller=\Ideal\Field\Addon"></script>
HTML;
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function getValue(): string
    {
        $value = parent::getValue();
        if (empty($value) || $value === 'null') {
            return json_encode([], JSON_THROW_ON_ERROR);
        }

        // Восстановление названия подключённых аддонов, т.к. по умолчанию текстовое название
        // аддона не указывается.

        $arr = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        foreach ($arr as $k => $v) {
            $addonVar = $v[1];
            $addonName = $v[2] ?? '';

            $class = Util::getClassName($addonVar, 'Addon') . '\\AdminModel';

            /** @var \Ideal\Core\Admin\Model $model */
            $model = new $class('');
            $arr[$k][2] = $addonName === '' ? $model->params['name'] : $addonName; // если почему-то в БД сбросится
        }

        $options = (defined('JSON_UNESCAPED_UNICODE')) ? JSON_UNESCAPED_UNICODE : 0;
        return json_encode($arr, JSON_THROW_ON_ERROR | $options);
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function parseInputValue(bool $isCreate): array
    {
        $item = parent::parseInputValue($isCreate);

        // TODO Дли типа данных аддон - нужно распарсить его элементы
        $addonsData = json_decode($item['value'], true, 512, JSON_THROW_ON_ERROR);
        foreach ($addonsData as $addonData) {
            [$tabID, $addonType] = $addonData;
            $addonName = Util::getClassName($addonType, 'Addon') . '\\AdminModel';
            $addon = new $addonName('не имеет значения, т.к. только парсим ввод пользователя');
            $explodeAddonType = explode('_', $addonType);
            $groupName = strtolower(end($explodeAddonType)) . '-' . $tabID;
            $addon->setParentModel($this->model);
            $addon->setFieldsGroup($groupName);
            $item['items'][$tabID] = $addon->parseInputParams($isCreate);
        }
        return $item;
    }
}
