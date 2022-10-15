<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

// @codingStandardsIgnoreFile
namespace Ideal\Core;

use Exception;
use Ideal\Field\Url;
use JsonException;
use RuntimeException;

abstract class Model
{

    public $fields;

    /** @var bool Флаг 404-ошибки */
    public bool $is404 = false;

    public array $params;

    protected string $_table;

    protected $module;

    protected array $pageData;

    protected int $pageNum = 1;

    protected string $pageNumTitle = ' | Страница [N]';

    protected string $parentUrl = '';

    protected array $path = [];

    protected string $prevStructure;

    /** @var Model Используется только в Addon для обозначения модели-владельца аддона */
    protected $parentModel;

    protected string $fieldsGroup = 'general';

    /**
     * @param $prevStructure
     * @throws Exception
     * @noinspection MultiAssignmentUsageInspection
     */
    public function __construct($prevStructure)
    {
        $this->prevStructure = $prevStructure;

        $config = Config::getInstance();

        $parts = preg_split('/[_\\\\]+/', get_class($this));
        $this->module = $parts[0];

        $type = $parts[1]; // Structure или Addon
        $structureName = $parts[2];
        $structureFullName = $this->module . '_' . $structureName;

        if ($structureName === 'Home') {
            $type = 'Home';
        }

        switch ($type) {
            case 'Home':
                // Находим начальную структуру
                $structures = $config->structures;
                $structure = reset($structures);
                $type = $parts[1];
                $structureName = $structure['structure'];
                $structureName = substr($structureName, strpos($structureName, '_') + 1);
                $structure = $config->getStructureByName($structure['structure']);
                $className = $config->getStructureClass($structure['structure'], 'Config');
                $cfg = new $className();
                break;
            case 'Structure':
                $structure = $config->getStructureByName($structureFullName);
                $className = $config->getStructureClass($structure['structure'], 'Config');
                $cfg = new $className();
                break;
            case 'Addon':
                $className = $this->module . '\\Addon\\' . $structureName . '\\Config';
                $cfg = new $className();
                break;
            default:
                throw new RuntimeException('Неизвестный тип: ' . $type);
        }

        $this->params = $cfg::$params;
        $this->fields = $cfg::$fields;

        $this->_table = strtolower($config->db['prefix'] . $this->module . '_' . $type . '_' . $structureName);
    }

    /**
     * Определение сокращённого имени структуры Модуль_Структура по имени этого класса
     *
     * @return string Сокращённое имя структуры, используемое в БД
     */
    public function getStructureName(): string
    {
        $parts = explode('\\', static::class);

        return $parts[0] . '_' . $parts[2];
    }

    /**
     * @noinspection MultipleReturnStatementsInspection
     */
    public function detectActualModel()
    {
        $config = Config::getInstance();
        $model = $this;
        $count = count($this->path);

        $class = get_class($this);
        if ($class === \Ideal\Structure\Home\Site\Model::class) {
            // В случае если у нас открыта главная страница, не нужно переопределять модель как обычной страницы
            return $model;
        }

        if ($count > 1) {
            $end = $this->path[($count - 1)];

            // Некоторые конечные структуры могут не иметь выбора типа раздела.
            // Значит и не будет поля "structure", тогда отдаём ранее найденную модель.
            if (!isset($end['structure'])) {
                return $model;
            }
            
            $prev = $this->path[($count - 2)];

            $endClass = ltrim(Util::getClassName($end['structure'], 'Structure'), '\\');
            $thisClass = get_class($this);

            // Проверяем, соответствует ли класс объекта вложенной структуре
            if (strpos($thisClass, $endClass) === false) {
                // Если структура активного элемента не равна структуре предыдущего элемента,
                // то нужно инициализировать модель структуры активного элемента
                $name = explode('\\', get_class($this));
                $modelClassName = Util::getClassName($end['structure'], 'Structure') . '\\' . $name[3] . '\\Model';
                $prevStructure = $config->getStructureByName($prev['structure']);
                /* @var $model Model */
                $model = new $modelClassName($prevStructure['ID'] . '-' . $end['ID']);
                // Передача всех данных из одной модели в другую
                $model = $model->setVars($this);
            }
        }
        return $model;
    }

    /**
     * Установка свойств объекта по данным из массива $vars
     *
     * Вызывается при копировании данных из одной модели в другую
     *
     * @param object $model Массив переменных объекта
     * @return $this Либо ссылка на самого себя, либо новый объект модели
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setVars(object $model)
    {
        $vars = get_object_vars($model);
        foreach ($vars as $k => $v) {
            if (in_array($k, ['_table', 'module', 'params', 'fields', 'prevStructure'])) {
                continue;
            }
            $this->$k = $v;
        }
        return $this;
    }

    // Получаем информацию о странице

    /**
     * Определение пути с помощью prev_structure по инициализированному $pageData
     *
     * @return array Массив, содержащий элементы пути к $pageData
     * @throws Exception
     */
    public function detectPath(): array
    {
        $config = Config::getInstance();

        // Определяем локальный путь в этой структуре
        $localPath = $this->getLocalPath();

        // По первому элементу в локальном пути, определяем, какую структуру нужно вызвать
        if (isset($localPath[0])) {
            $first = $localPath[0];
        } else {
            $first['prev_structure'] = $this->prevStructure;
        }

        [$prevStructureId, $prevElementId] = explode('-', $first['prev_structure']);
        $structure = $config->getStructureByPrev($first['prev_structure']);

        if ((int)$prevStructureId === 0) {
            // Если предыдущая структура стартовая — заканчиваем
            array_unshift($localPath, $structure);
            return $localPath;
        }

        // Если предыдущая структура не стартовая —
        // инициализируем её модель и продолжаем определение пути в ней
        $className = Util::getClassName($structure['structure'], 'Structure') . '\\Site\\Model';

        $structure = new $className('');
        $structure->setPageDataById($prevElementId);

        $path = $structure->detectPath();
        return array_merge($path, $localPath);
    }

    // Устанавливаем информацию о странице

    /**
     * Построение пути в рамках одной структуры.
     * Этот метод обязательно должен быть переопределён перед использованием.
     * Если он не будет переопределён, то будет вызвано исключение.
     *
     * @throws Exception
     */
    protected function getLocalPath(): array
    {
        throw new RuntimeException('Вызов не переопределённого метода getLocalPath');
    }

    /**
     * @param int|null $page Номер отображаемой страницы
     * @return array Полученный список элементов
     */
    public function getList(?int $page = null): array
    {
        $db = Db::getInstance();

        if (!empty($this->filter)) {
            $_sql = $this->filter->getSql();
        } else {
            $where = ($this->prevStructure !== '') ? "e.prev_structure='$this->prevStructure'" : '';
            $where = $this->getWhere($where);
            $order = $this->getOrder();
            $_sql = "SELECT e.* FROM $this->_table AS e $where $order";
        }

        if ($page === null) {
            $this->setPageNum($page);
        } else {
            // Определяем кол-во отображаемых элементов на основании названия класса
            $class = strtolower(get_class($this));
            $class = explode('\\', trim($class, '\\'));
            $nameParam = ($class[3] === 'admin') ? 'elements_cms' : 'elements_site';
            $onPage = $this->params[$nameParam];

            $page = $this->setPageNum($page);
            $start = ($page - 1) * $onPage;

            $_sql .= " LIMIT $start, $onPage";
        }

        return $db->select($_sql);
    }

    /**
     * Добавление к where-запросу фильтра по category_id
     *
     * @param string $where Исходная WHERE-часть
     * @return string Модифицированная WHERE-часть, с расширенным запросом, если установлена GET-переменная category
     */
    protected function getWhere(string $where): string
    {
        if ($where !== '') {
            // Убираем из строки начальные команды AND или OR
            $where = trim($where);
            $where = preg_replace('/(^AND)|(^OR)/i', '', $where);
            $where = 'WHERE ' . $where;
        }

        return $where;
    }

    /**
     * Формирование ORDER-части запроса
     *
     * @return string Сформированная ORDER-часть
     */
    protected function getOrder(): string
    {
        $request = new Request();
        $order = 'ORDER BY e.';
        if ($request->get('asc')) {
            $order .= $request->get('asc');
        } elseif ($request->get('desc')) {
            $order .= $request->get('desc') . ' DESC';
        } else {
            $order .= $this->params['field_sort'];
        }

        return $order;
    }

    /**
     * Получение из БД данных открытой страницы (в том числе и подключённых аддонов)
     *
     * @return array
     * @throws Exception
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function getPageData(): array
    {
        if ($this->pageData === null) {
            $this->initPageData();
        }
        return $this->pageData;
    }

    public function setPageData($pageData): void
    {
        $this->pageData = $pageData;
    }

    /**
     * Установка pageData по ID элемента
     *
     * @param int $id ID элемента
     * @throws RuntimeException В случае, если нет элемента с указанным ID
     * @throws JsonException
     */
    public function initPageDataById(int $id): void
    {
        $db = Db::getInstance();
        $result = $db->select('SELECT * FROM ' . $this->_table . ' WHERE ID=:id', ['id' => $id]);
        if (empty($result[0])) {
            throw new RuntimeException('Элемент не найден');
        }

        $this->initPageData($result[0]);
    }

    /**
     * @throws JsonException
     */
    public function initPageData($pageData = null): void
    {
        if ($pageData === null) {
            $this->pageData = end($this->path);
        } else {
            $this->pageData = $pageData;
        }

        // Получаем переменные шаблона
        $config = Config::getInstance();
        foreach ($this->fields as $k => $v) {
            // Пропускаем все поля, которые не являются аддоном
            if (strpos($v['type'], '_Addon') === false) {
                continue;
            }

            // В случае, если 404 ошибка, и нужной страницы в БД не найти
            if (!isset($this->pageData[$k])) {
                continue;
            }

            // Определяем структуру на основании названия класса
            $structure = $config->getStructureByClass(get_class($this));

            if ($structure === null) {
                // Не удалось определить структуру из конфига (Home)
                // Определяем структуру, к которой принадлежит последний элемент пути
                $prev = count($this->path) - 2;
                if ($prev >= 0) {
                    $prev = $this->path[$prev];
                    $structure = $config->getStructureByName($prev['structure']);
                } else {
                    throw new RuntimeException('Не могу определить структуру для шаблона');
                }
            }

            // Обходим все аддоны, подключенные к странице
            $addonsInfo = json_decode($this->pageData[$k], true, 512, JSON_THROW_ON_ERROR);

            if (is_array($addonsInfo)) {
                foreach ($addonsInfo as $addonInfo) {
                    // Инициализируем модель аддона
                    $class = strtolower(get_class($this));
                    $class = explode('\\', trim($class, '\\'));
                    $modelName = ($class[3] === 'admin') ? '\\AdminModel' : '\\SiteModel';
                    $className = Util::getClassName($addonInfo[1], 'Addon') . $modelName;
                    $prevStructure = $structure['ID'] . '-' . $this->pageData['ID'];
                    $addon = new $className($prevStructure);
                    $addon->setParentModel($this);
                    [, $fieldsGroup] = explode('_', $addonInfo[1]);
                    $addon->setFieldsGroup(strtolower($fieldsGroup) . '-' . $addonInfo[0]);
                    $pageData = $addon->getPageData();
                    if (!empty($pageData)) {
                        $this->pageData['addons'][] = $pageData;
                    }
                }
            }
        }
    }

    /**
     * Получение листалки для шаблона и стрелок вправо/влево
     *
     * @param string $pageName Название get-параметра, содержащего страницу
     * @return null|array
     * @noinspection DuplicatedCode
     */
    public function getPager(string $pageName): ?array
    {
        // По заданному названию параметра страницы определяем номер активной страницы
        $request = new Request();
        $page = $this->setPageNum((int)$request->get($pageName));

        // Строка запроса без нашего параметра номера страницы
        $query = $request->getQueryWithout($pageName);

        // Определяем кол-во отображаемых элементов на основании названия класса
        $class = strtolower(get_class($this));
        $class = explode('\\', trim($class, '\\'));
        $nameParam = ($class[3] === 'admin') ? 'elements_cms' : 'elements_site';
        $onPage = $this->params[$nameParam];

        $countList = $this->getListCount();

        if (($countList > 0) && (ceil($countList / $onPage) < $page)) {
            // Если для запрошенного номера страницы нет элементов - выдать 404
            $this->is404 = true;
            return null;
        }

        $pagination = new Pagination();
        // Номера и ссылки на доступные страницы
        $pager['pages'] = $pagination->getPages($countList, $onPage, $page, $query, $pageName);
        $pager['prev'] = $pagination->getPrev(); // ссылка на предыдущую страницу
        $pager['next'] = $pagination->getNext(); // ссылка на следующую страницу
        $pager['total'] = $countList; // общее количество элементов в списке
        $pager['num'] = $onPage; // количество элементов на странице

        return $pager;
    }

    /**
     * Получить общее количество элементов в списке
     *
     * @return int Количество элементов в списке
     */
    public function getListCount(): int
    {
        $db = Db::getInstance();

        if (!empty($this->filter)) {
            $_sql = $this->filter->getSqlCount();
        } else {
            $where = ($this->prevStructure !== '') ? "e.prev_structure='$this->prevStructure'" : '';
            $where = $this->getWhere($where);

            // Считываем все элементы первого уровня
            $_sql = "SELECT COUNT(e.ID) FROM $this->_table AS e $where";
        }
        $list = $db->select($_sql);

        return $list[0]['COUNT(e.ID)'];
    }

    /**
     * Получение номера отображаемой страницы
     *
     * @return int Номер отображаемой страницы
     */
    public function getPageNum(): int
    {
        return $this->pageNum ?? 1;
    }

    public function getParentUrl(): string
    {
        if ($this->parentUrl !== '') {
            return $this->parentUrl;
        }

        $url = new Url\Model();
        $this->parentUrl = $url->setParentUrl($this->path);

        return $this->parentUrl;
    }

    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * Получение названия основной таблицы модели
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->_table;
    }

    public function setPath($path): void
    {
        $this->path = $path;
        $end = end($path);
        if (empty($end['prev_structure'])) {
            // это 404-ая страница, prev_structure устанавливать не надо
            if (!empty($end['ID'])) {
                // непонятно, когда такой случай может быть, скорее всего это ошибка
                throw new RuntimeException('prev_structure don`t set');
            }
        } else {
            $this->prevStructure = $end['prev_structure'];
        }
    }

    public function getPrevStructure(): string
    {
        return $this->prevStructure;
    }

    public function setPrevStructure($prevStructure): void
    {
        $this->prevStructure = $prevStructure;
    }

    public function setPageDataById($id): void
    {
        $db = Db::getInstance();

        $_sql = "SELECT * FROM $this->_table WHERE ID=:id";
        $pageData = $db->select($_sql, ['id' => $id]);
        if (isset($pageData[0]['ID'])) {
            // TODO сделать обработку ошибки, когда по ID ничего не нашлось
            $this->setPageData($pageData[0]);
        }
    }

    /**
     * Установка номера отображаемой страницы
     *
     * С номером страницы всё понятно, а вот $pageNumTitle позволяет изменить стандартный шаблон
     * суффикса для листалки ` | Страница [N]` на любой другой суффикс, где
     * вместе [N] будет подставляться номер страницы.
     *
     * @param null|int $pageNum Номер отображаемой страницы
     * @param null|string $pageNumTitle Строка для замены стандартного суффикса листалки в title
     * @return int Безопасный номер страницы
     */
    public function setPageNum(?int $pageNum, ?string $pageNumTitle = null): int
    {
        if (isset($this->pageNum)) {
            return $this->pageNum;
        }
        $this->pageNum = 0;
        if ($pageNum !== null) {
            $page = (int)substr($pageNum, 0, 10); // отсекаем всякую ерунду и слишком большие числа в листалке
            // Если номер страницы отрицательный или ноль, то устанавливаем первую страницу
            $this->pageNum = ($page <= 0) ? 1 : $page;
            if ($pageNum !== 0 && $this->pageNum !== $pageNum) {
                // Если корректный номер страницы не совпадает с переданным - 404 ошибка
                $this->is404 = true;
            }
        }

        if ($pageNumTitle !== null) {
            $this->pageNumTitle = $pageNumTitle;
        }

        return $this->pageNum;
    }

    /**
     * Метод используется только в моделях Addon для установки модели владельца этого аддона
     *
     * @param $model
     */
    public function setParentModel($model): void
    {
        $this->parentModel = $model;
    }

    /**
     * Метод используется в полях аддонов для получения доступа к модели владельца аддона
     *
     * @return Model
     */
    public function getParentModel()
    {
        return $this->parentModel;
    }

    public function setFieldsGroup($name): void
    {
        $this->fieldsGroup = $name;
    }

    /**
     * "Умная" установка 404: если флаг уже установлен в true, то не сбрасываем его
     *
     * @param bool $is404 Устанавливаемый флаг 404-ой ошибки
     *
     * @return $this
     */
    public function set404(bool $is404): self
    {
        $this->is404 = $this->is404 || $is404;

        return $this;
    }

    /**
     * Формирование prev_structure из текущего элемента
     *
     * @return string Строка prev_structure
     * @throws Exception
     */
    public function getSelfStructure(): string
    {
        $data = $this->getPageData();
        if (isset($data['prev_structure'])) {
            $config = Config::getInstance();
            $structure = $config->getStructureByClass(get_class($this));
            $prevStructure = $structure['ID'] . '-' . $data['ID'];
        } else {
            throw new RuntimeException('No prev_structure in data');
        }
        return $prevStructure;
    }
}
