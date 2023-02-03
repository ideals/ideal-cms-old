<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core\Admin;

use Exception;
use Ideal\Addon\AbstractAdminModel;
use Ideal\Core;
use Ideal\Core\Config;
use Ideal\Core\Db;
use Ideal\Core\Request;
use Ideal\Core\Util;
use Ideal\Field\AbstractController as FieldAbstractController;
use Ideal\Structure\Log\Model as LogModel;
use JsonException;
use RuntimeException;

abstract class Model extends Core\Model
{

    /**
     * @param $prevStructure
     * @throws Exception
     */
    public function __construct($prevStructure)
    {
        parent::__construct($prevStructure);

        $class = strtolower(get_class($this));
        $class = explode('\\', trim($class, '\\'));
        $nameParam = ($class[3] === 'admin') ? 'elements_cms' : 'elements_site';

        $request = new Request();

        if (empty($this->params[$nameParam])) {
            // Если не инициализировано поле с количеством элементов — значит мы редактируем элемент
            // и установка количества элементов на странице не требуется
            return;
        }

        $this->params[$nameParam] = empty($request->num) ? $this->params[$nameParam] : $request->num;
    }

    /**
     * Создание нового элемента структуры
     *
     * @param $result
     * @param string $groupName
     * @return array|mixed
     * @throws JsonException
     */
    public function createElement($result, string $groupName = 'general')
    {
        // Из общего списка введённых данных выделяем те, что помечены general
        foreach ($result['items'] as $v) {
            [$group, $field] = explode('_', $v['fieldName'], 2);

            if ($group === $groupName && $field === 'prev_structure' && $v['value'] === '') {
                $result['items'][$v['fieldName']]['value'] = $this->prevStructure;
                $v['value'] = $this->prevStructure;
            }

            // Если в значении NULL, то сохранять это поле не надо
            if ($v['value'] === null) {
                continue;
            }

            $groups[$group][$field] = $v['value'];
        }

        if (isset($groups[$groupName]['ID'])) {
            unset($groups[$groupName]['ID']);
        }

        if (!isset($groups[$groupName])) {
            throw new RuntimeException('Не определена группа ' . $groupName);
        }

        $db = Db::getInstance();

        $id = $db->insert($this->_table, $groups[$groupName]);

        if ($id) {
            $result['items'][$groupName . '_ID']['value'] = $id;
            $groups[$groupName]['ID'] = $id;

            if (isset($result['sqlAdd'][$groupName]) && ($result['sqlAdd'][$groupName] !== '')) {
                $sqlAdd = str_replace(['{{ table }}', '{{ objectId }}'], [$this->_table, $id], $result['sqlAdd'][$groupName]);
                $sqlAdd = explode(';', $sqlAdd);
                foreach ($sqlAdd as $_sql) {
                    if ($_sql !== '') {
                        $db->query($_sql);
                    }
                }
            }

            $result = $this->saveAddData($result, $groups, $groupName, true);

            // Устанавливаем данные только что созданного элемента для использования в логах
            $this->setPageDataById($id);
        } else {
            // Добавить запись не получилось
            $result['isCorrect'] = 0;
            $result['errorText'] = 'Ошибка при добавлении в БД. ' . $db->error;
        }
        return $result;
    }

    /**
     * Обработка переменных от дополнительных табов с аддонами
     *
     * @param array $result
     * @param array $groups
     * @param string $groupName
     * @param bool $isCreate
     * @return array
     * @throws JsonException
     * @throws Exception
     */
    public function saveAddData(array $result, array $groups, string $groupName, bool $isCreate = false): array
    {
        $config = Config::getInstance();

        // Считываем данные дополнительных табов
        foreach ($this->fields as $fieldName => $field) {
            if (strpos($field['type'], '_Addon') === false) {
                continue;
            }

            $addonsInfo = json_decode($groups[$groupName][$fieldName], true, 512, JSON_THROW_ON_ERROR);

            // Сохраняем информацию из аддонов
            foreach ($addonsInfo as $addonInfo) {
                $tempAddonInfo = explode('_', $addonInfo[1]);
                $addonGroupName = strtolower(end($tempAddonInfo)) . '-' . $addonInfo[0];
                $addonData = $groups[$addonGroupName];
                $end = end($this->path);
                $prevStructure = $config->getStructureByName($end['structure']);

                // значение преструктуры основной структуры
                // TODO переделать собирание преструктуры, чтобы значение брались из правильного места
                $addonData['prev_structure'] = $prevStructure['ID'] . '-' . $groups[$groupName]['ID'];
                if (empty($addonData['ID'])) {
                    // Для случая, если вдруг элемент был создан, а аддон у него был не прописан
                    $isCreate = true;
                }
                if ($isCreate) {
                    unset($addonData['ID']);
                }

                // todo вызывать AdminModel, а не через декоратор
                $addonModelName = Util::getClassName($addonInfo[1], 'Addon') . '\\AdminModel';

                /* @var $addonModelName Model */
                $addonModel = new $addonModelName($addonData['prev_structure']);
                if ($isCreate) {
                    // Записываем данные шаблона в БД и в $result
                    $result = $addonModel->createElement($result, $addonGroupName);
                } else {
                    $addonModel->setPageDataById($addonData['ID']);
                    $result = $addonModel->saveElement($result, $addonGroupName);
                }
            }


            // Удаляем информацию об удалённых аддонах
            $pageData = $this->getPageData();
            if ((isset($pageData['addon']) && $pageData['addon'] !== 'null')) {
                $preSaveAddonsInfo = json_decode($pageData['addon'], true, 512, JSON_THROW_ON_ERROR);
            } else {
                $preSaveAddonsInfo = [];
            }
            if (!empty($preSaveAddonsInfo)) {
                foreach ($preSaveAddonsInfo as $preSaveAddonInfo) {
                    // Удаляем информацию об аддоне из старого списка, если его нет в новом.
                    if (!in_array($preSaveAddonInfo, $addonsInfo, true)) {
                        $end = end($this->path);
                        $preSaveAddonPrevStructure = $config->getStructureByName($end['structure']);

                        // значение преструктуры основной структуры
                        // TODO переделать собирание преструктуры, чтобы значение брались из правильного места
                        $preSaveAddonDataPrevStructure = $preSaveAddonPrevStructure['ID']
                            . '-' . $groups[$groupName]['ID'];
                        $this->deleteAddon($preSaveAddonInfo, $preSaveAddonDataPrevStructure);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @param $result
     * @param string $groupName
     * @return array
     * @throws JsonException
     */
    public function saveElement($result, string $groupName = 'general'): array
    {
        // Из общего списка введённых данных выделяем те, что помечены general
        foreach ($result['items'] as $v) {
            [$group, $field] = explode('_', $v['fieldName'], 2);

            if ($group === $groupName && $field === 'prev_structure' && $v['value'] === '') {
                $result['items'][$v['fieldName']]['value'] = $this->prevStructure;
                $v['value'] = $this->prevStructure;
            }

            // Если в значении NULL, то сохранять это поле не надо
            if ($v['value'] === null) {
                continue;
            }

            // Если у этого поля не прописан sql, то сохранять его не надо
            if ($group === $groupName && empty($this->fields[$field]['sql'])) {
                continue;
            }

            $groups[$group][$field] = $v['value'];
        }

        if (!isset($groups[$groupName])) {
            throw new RuntimeException('Не определена группа ' . $groupName);
        }

        $db = Db::getInstance();

        $db->update($this->_table)->set($groups[$groupName]);
        $db->where('ID = :id', ['id' => $groups[$groupName]['ID']])->exec();
        if ($db->errno > 0) {
            // Если при попытке обновления произошла ошибка не выполнять доп. запросы, а сообщить об этом пользователю
            $result['isCorrect'] = false;
            $result['errorText'] = $db->error . PHP_EOL . 'Query: ' . $db->getSql();
            return $result;
        }

        if (isset($result['sqlAdd'][$groupName]) && ($result['sqlAdd'][$groupName] !== '')) {
            $sqlAdd = str_replace(['{{ table }}', '{{ objectId }}'], [$this->_table, $groups[$groupName]['ID']], $result['sqlAdd'][$groupName]);
            $sqlAdd = explode(';', $sqlAdd);
            foreach ($sqlAdd as $_sql) {
                if ($_sql !== '') {
                    $db->query($_sql);
                }
            }
        }

        return $this->saveAddData($result, $groups, $groupName);
    }

    /**
     * @param $path
     * @param $par
     * @return mixed
     */
    public function detectPageByIds($path, $par)
    {
        throw new RuntimeException('Попытка вызвать не переопределённый метод detectPageByIds в классе ' . get_class($this));
    }

    public function getFieldsList($tab): string
    {
        $tabsContent = '';
        foreach ($tab as $fieldName => $field) {
            $fieldClass = Util::getClassName($field['type'], 'Field') . '\\Controller';
            /* @var $fieldModel FieldAbstractController */
            /** @noinspection PhpUndefinedMethodInspection */
            $fieldModel = $fieldClass::getInstance();
            $fieldModel->setModel($this, $fieldName, $this->fieldsGroup);
            $tabsContent .= $fieldModel->showEdit();
        }
        return $tabsContent;
    }

    public function getHeaderNames(): array
    {
        $headers = $this->getHeaders();
        $sortFieldArray = $this->getSortField();
        $headerNames = [];

        // Составляем список названий колонок
        foreach ($headers as $v) {
            $headerNames[$v] = [$this->fields[$v]['label'], $v,];
            if (isset($sortFieldArray[$v])) {
                $headerNames[$v][2] = $sortFieldArray[$v];
            }
        }
        return $headerNames;
    }

    public function getHeaders(): array
    {
        $headers = [];

        // Убираем символы ! из заголовков
        foreach ($this->params['field_list'] as $v) {
            $column = explode('!', $v);
            $headers[] = $column[0];
        }

        return $headers;
    }

    public function getTitle(): string
    {
        $config = Config::getInstance();

        return $this->getHeader() . ' - админка ' . $config->domain;
    }

    public function getHeader()
    {
        $end = end($this->path);
        return $end['name'];
    }

    public function getToolbar(): string
    {
        return '';
    }

    /**
     * Если всё правильно - возвращает массив для сохранения,
     * если неправильно - массив с сообщениями об ошибках.
     *
     * @param bool $isCreate
     * @return array
     * @throws Exception
     */
    public function parseInputParams(bool $isCreate = false): array
    {
        $result = [
            'isCorrect' => true,
            'errorTabs' => [],
            'items' => []
        ];

        // Для каждого поля прописываем имя вкладки, в которой оно находится
        $tabs = ['tab1'];
        foreach ($this->fields as $fieldName => $field) {
            if ($this->fieldsGroup !== 'general') {
                // Пока на каждый шаблон можно использовать только одну вкладку
                $this->fields[$fieldName]['realTab'] = $this->fieldsGroup;
                continue;
            }
            // Для каждой записи в структуре может быть несколько вкладок
            $tab = 'tab1';
            if (isset($field['tab'])) {
                if (!array_key_exists($field['tab'], $tabs)) {
                    $tabs[$field['tab']] = 'tab' . ((int) substr(end($tabs), 3) + 1);
                }
                $tab = $tabs[$field['tab']];
            }
            $this->fields[$fieldName]['realTab'] = $tab;
        }

        $result['sqlAdd'][$this->fieldsGroup] = '';

        // Проходимся по всем полям этого типа и проверяем их корректность
        foreach ($this->fields as $fieldName => $field) {
            // TODO добавить валидаторы

            // Определяем класс контроллера для соответствующего поля
            $fieldClass = Util::getClassName($field['type'], 'Field') . '\\Controller';
            /* @var $fieldModel FieldAbstractController */
            /** @noinspection PhpUndefinedMethodInspection */
            $fieldModel = $fieldClass::getInstance();
            $fieldModel->setModel($this, $fieldName, $this->fieldsGroup);
            // Получаем данные, введённые пользователем
            $item = $fieldModel->parseInputValue($isCreate);

            if (isset($item['items'])) {
                // Если есть вложенные элементы - добавляем их к результатам.
                // Проверяем на наличие нескольких вложенностей
                if (is_array($item['items']) && !isset($item['items']['items'])) {
                    foreach ($item['items'] as $value) {
                        $result['items'] = array_merge($result['items'], $value['items']);

                        // Добавляем дополнительные запросы от вложенных элементов
                        $result['sqlAdd'] = array_merge($result['sqlAdd'], $value['sqlAdd']);
                        if (!$value['isCorrect']) {
                            $result['isCorrect'] = false;
                        }
                    }
                } else {
                    $result['items'] = array_merge($result['items'], $item['items']['items']);
                    if (!$item['items']['isCorrect']) {
                        $result['isCorrect'] = false;
                    }
                }
                unset($item['items']);
            }

            if (!isset($item['sqlAdd'])) {
                // Свойство sqlAdd должно быть обязательно определено для каждого редактируемого поля
                throw new RuntimeException('Отсутствует свойство sqlAdd в поле ' . print_r($item, true));
            }

            $result['sqlAdd'][$this->fieldsGroup] .= $item['sqlAdd'];

            $item['realTab'] = $field['realTab'];
            $result['items'][$item['fieldName']] = $item;
        }

        // Проверяем все поля на ошибки, если ошибки есть — составляем список табов, в которых ошибки
        foreach ($result['items'] as $item) {
            // Если есть сообщение об ошибке - значит общий результат - ошибка
            $result['isCorrect'] = (($item['message'] === '') && $result['isCorrect']);

            // Составляем список вкладок, в которых возникли ошибки
            if (($item['message'] !== '') && (!in_array($item['realTab'], $result['errorTabs'], true))) {
                $result['errorTabs'][] = $item['realTab'];
            }
        }

        return $result;
    }

    /**
     * Установка пустого pageData
     */
    public function setPageDataNew(): void
    {
        $this->setPageData([]);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function delete(): bool
    {
        $config = Config::getInstance();
        $pageData = $this->getPageData();

        // Если есть подключенные аддоны, то сперва удаляем информацию из их таблиц
        if (isset($pageData['addon']) && !empty($pageData['addon'])) {
            $addonsInfo = json_decode($pageData['addon'], true, 512, JSON_THROW_ON_ERROR);

            $end = end($this->path);
            $prevStructure = $config->getStructureByName($end['structure']);
            $addonDataPrevStructure = $prevStructure['ID'] . '-' . $pageData['ID'];

            foreach ($addonsInfo as $addonInfo) {
                $this->deleteAddon($addonInfo, $addonDataPrevStructure);
            }
        }
        // todo проверка успешности удаления

        return true;
    }

    /**
     * @param string $action Совершаемое действие
     * @throws Exception
     */
    public function saveToLog(string $action): void
    {
        $logModel = new LogModel();
        $context = [
            'model' => $this,
            'type' => 'admin',
        ];
        $pageData = $this->getPageData();
        $altName = reset($this->params['field_list']);
        $logName = empty($pageData['name']) ? $pageData[$altName] : $pageData['name'];
        $message = $action . ' «' . $logName . '»';
        $logModel->info($message, $context);
    }

    /**
     * @param $addonInfo
     * @param $addonDataPrevStructure
     * @throws Exception
     */
    protected function deleteAddon($addonInfo, $addonDataPrevStructure): void
    {
        $tempDeletedAddonInfo = explode('_', $addonInfo[1]);
        $deletedAddonGroupName = strtolower(end($tempDeletedAddonInfo)) . '-' . $addonInfo[0];

        $addonModelName = Util::getClassName($addonInfo[1], 'Addon') . '\\AdminModel';
        /** @var AbstractAdminModel $deletedAddonModel */
        $deletedAddonModel = new $addonModelName($addonDataPrevStructure);
        $deletedAddonModel->setFieldsGroup($deletedAddonGroupName);
        $deletedAddonModel->setPageDataByPrevStructure($addonDataPrevStructure);
        // Удаляем данные об аддоне
        $deletedAddonModel->delete();
    }

    /**
     * {@inheritdoc}
     */
    protected function getWhere(string $where): string
    {
        // Добавляем проверку на скрытие части страниц с помощью прав доступа
        $config = Config::getInstance();
        $structure = $config->getStructureByClass(get_class($this));
        $user = \Ideal\Structure\User\Model::getInstance();
        $aclTable = $config->db['prefix'] . 'ideal_structure_acl';
        /** @noinspection SqlResolve */
        $sqlAcl = "SELECT structure FROM $aclTable WHERE user_group_id='{$user->data['user_group']}' AND `show`=0";
        $where .= " AND CONCAT('{$structure['ID']}-', e.ID) NOT IN ($sqlAcl)";

        return parent::getWhere($where);
    }

    /**
     * Получение списка элементов с наложением списка прав доступа
     *
     * @param int $page Номер отображаемой страницы
     * @return array Полученный список элементов
     */
    public function getListAcl(int $page): array
    {
        $config = Config::getInstance();
        $structure = $config->getStructureByClass(get_class($this));
        $list = $this->getList($page);
        $ids = [];
        foreach ($list as $v) {
            $ids[$v['ID']] = $structure['ID'] . '-' . $v['ID'];
        }
        $aclModel = new \Ideal\Structure\Acl\Admin\Model();
        $acl = $aclModel->getAcl($ids);
        foreach ($list as $k => $v) {
            if (!empty($acl[$ids[$v['ID']]])) {
                $list[$k]['acl'] = $acl[$ids[$v['ID']]];
            }
        }
        return $list;
    }

    /**
     * Получение поля по которому должна идти сортировка
     *
     * @return array Массив с названием поля и порядком сортировки по нему
     */
    private function getSortField(): array
    {
        // Определяем название поля и порядок сортировки по умолчанию
        $fieldSort = explode(' ', $this->params['field_sort']);

        $sortArray = [$fieldSort[0] => empty($fieldSort[1]) ? 'asc' : strtolower($fieldSort[1])];
        $request = new Request();

        // Проверяем была ли применена сортировка по возрастанию
        $ascSort = $request->get('asc');
        if ($ascSort) {
            $sortArray = [$ascSort => 'asc'];
        }

        // Проверяем была ли применена сортировка по убыванию
        $descSort = $request->get('desc');
        if ($descSort) {
            $sortArray = [$descSort => 'desc'];
        }
        return $sortArray;
    }
}
