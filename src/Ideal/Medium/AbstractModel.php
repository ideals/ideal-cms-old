<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Medium;

use Exception;
use Ideal\Core\Admin\Model;
use Ideal\Core\Config;
use Ideal\Core\Db;
use RuntimeException;

/**
 * Абстрактный класс, реализующий основные методы для семейства классов Medium'а
 *
 * Медиумы обеспечивают предоставление данных для Select и SelectMulti, а также их наследников.
 * А также генерируют запросы для сохранения связи многие ко многим в полях вида SelectMulti.
 *
 */
class AbstractModel
{

    /** @var string Название редактируемого поля */
    protected string $fieldName;

    /** @var array Список полей в медиум-таблице, если она есть */
    protected $fields;

    /** @var  Model Модель редактируемого элемента */
    protected Model $obj;

    /** @var array Настройки медиума из конфигурационного файла */
    protected $params;

    /** @var string Название промежуточной таблицы, которая связывает владельца и список элементов */
    protected string $table;

    /**
     * @param Model $obj
     * @param string $fieldName
     * @throws Exception
     * @noinspection PhpUndefinedFieldInspection
     */
    public function __construct(Model $obj, string $fieldName)
    {
        $config = Config::getInstance();

        $this->obj = $obj;
        $this->fieldName = $fieldName;

        $this->table = $config->getTableByClass(get_class($this));
        $configClass = $config->getTypeClass(get_class($this), 'Config');

        $this->params = $configClass::$params;
        $this->fields = $configClass::$fields;
    }

    /**
     * Получение списка элементов для отображения в select'е или другом поле редактирования
     *
     * @throws RuntimeException
     * @return array
     */
    public function getList(): array
    {
        throw new RuntimeException('Вызов в медиуме ' . get_class($this) . ' не переопределённого метода getList');
    }

    /**
     * Получение дополнительных sql-запросов для сохранения списка выбранных элементов для владельца
     *
     * @param mixed $newValue
     * @return string
     */
    public function getSqlAdd($newValue): string
    {
        [$ownerField, $elementsField] = array_keys($this->fields);

        // Удаляем все существующие связи владельца и элементов
        $_sql = "DELETE FROM $this->table WHERE $ownerField='{{ objectId }}';";

        if (!is_array($newValue) || (count($newValue) === 0)) {
            // Если $newValue не массив, значит ни один элемент не задан
            return $_sql;
        }

        // Добавляем связи владельца и элементов сделанные пользователем
        foreach ($newValue as $v) {
            $_sql .= "INSERT INTO $this->table SET $ownerField='{{ objectId }}', $elementsField='$v';";
        }

        return $_sql;
    }

    /**
     * Получение списка элементов выбранных в SelectMulti для этого владельца
     *
     * @return array Список выбранных элементов
     * @throws Exception
     */
    public function getValues(): array
    {
        [$ownerField, $elementsField] = array_keys($this->fields);
        $list = [];

        // Определяем владельца медиума
        $db = Db::getInstance();
        $owner = $this->obj->getPageData();

        // Если владельца нет (он только создаётся), то и связей нет
        if (count($owner) === 0) {
            return $list;
        }

        // Находим все медиумные связи между владельцем и выбранными элементами в SelectMulti
        $_sql = "SELECT $elementsField FROM $this->table WHERE $ownerField='{$owner['ID']}'";
        $arr = $db->select($_sql);

        foreach ($arr as $v) {
            $list[] = $v[$elementsField];
        }

        return $list;
    }
}
