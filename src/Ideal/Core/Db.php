<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core;

use RuntimeException;
use mysqli;
use mysqli_result;

/**
 * Класс NewDb — обёртка над mysqli, добавляющий следующие улучшения
 * + одноразовое подключение к БД в рамках одного запуска php-интерпретатора
 * + класс сам обращается к настройкам подключения в конфигурационном файле CMS
 * + вспомогательные методы для запросов SELECT, INSERT, UPDATE, экранирующие входные параметры
 * + метод create, для создания таблиц на основе настроек CMS
 * В рамках CMS класс используется следующим образом
 *     $db = Db::getInstance();
 *     $par = array('time' = time(), 'active' = 1);
 *     $fields = array('table', 'example_table');
 *     $rows = $db->select('SELECT * FROM &table WHERE time < :time AND is_active = :active',
 *                     $par, $fields);
 * В переменной $rows окажется ассоциативный массив записей из таблицы `example_table`,
 * у которых поле `time` меньше чем текущее время, а поле `is_active` равно 1
 */

class Db extends mysqli
{

    /** @var array Массив для хранения подключений к разным БД */
    protected static array $instance;

    /** @var Memcache Экземпляр подключения к memcache */
    protected Memcache $cache;

    /** @var bool Флаг того, что следующий запрос надо попытаться взять из кэша */
    protected bool $cacheEnabled = false;

    /** @var string Название используемой БД для корректного кэширования запросов */
    protected string $dbName;

    /** @var string Название таблицы для запроса DELETE */
    protected string $deleteTableName = '';

    /** @var array Массив для хранения явно указанных таблиц при вызове cacheMe() */
    protected array $involvedTables;

    /** @var string Название таблицы для запроса UPDATE */
    protected string $updateTableName = '';

    /** @var array Массив для хранения пар ключ-значение метода set() */
    protected array $updateValues = [];

    /** @var array Массив для хранения пар ключ-значение метода where() */
    protected array $whereParams = [];

    /** @var string Строка с where-частью запроса */
    protected string $whereQuery = '';

    /** @var bool Флаг необходимости логирования ошибок, который ставится в true после каждого запроса */
    protected bool $logError = true;

    /**
     * Получение singleton-объекта подключённого к БД
     *
     * Если переменная $params не задана, то данные для подключения берутся из конфигурационного файла CMS.
     * В массиве $params должны быть следующие элементы:
     * host, login, password, name
     *
     * @param null|array $params Параметры подключения к БД
     * @return bool|Db Объект, подключённый к БД, false — в случае невозможности подключиться к БД
     * @noinspection MultipleReturnStatementsInspection
     */
    public static function getInstance(array $params = null)
    {
        $key = md5(serialize($params));

        if (!empty(self::$instance[$key])) {
            // Если singleton этого подключения инициализирован, возвращаем его
            return self::$instance[$key];
        }

        $config = Config::getInstance();

        if ($params === null) {
            // Если параметры подключения явно не заданы, берём их из конфигурации
            $params = $config->db;
        }

        $db = new Db($params['host'], $params['login'], $params['password'], $params['name']);

        if ($db->connect_errno) {
            Util::addError('Не удалось подключиться к MySQL: ' . $db->connect_error);
            return false;
        }

        // Работаем только с UTF-8
        $db->query('set character set utf8');
        $db->query('set names utf8');

        $db->dbName = $params['name'];

        if (isset($config->cache['memcache']) && $config->cache['memcache']) {
            // Если в настройках site_data.php включён memcache, подключаем его
            $db->cache = Memcache::getInstance();
        }

        self::$instance[$key] = $db;

        return $db;
    }

    /**
     * Выполняет запрос к базе данных
     *
     * @link http://php.net/manual/ru/mysqli.query.php
     * @param string $query
     * @param null|int $result_mode
     * @return bool|mysqli_result
     * @noinspection PhpMissingParamTypeInspection
     */
    public function query($query, $result_mode = null)
    {
        $result = parent::query($query, $result_mode);

        if ($this->logError && $error = $this->error) {
            Util::addError($error . PHP_EOL . 'Query: ' . $query);
        }

        // После выполнения каждой операции - устанавливаем флаг логирования ошибок, чтобы случайно их не пропустить
        $this->logError = true;

        return $result;
    }

    /**
     * Установка флага попытки получения из кэша результатов следующего select-запроса
     *
     * @param null|array $involvedTables Массив с именами таблиц, участвующих в запросе.
     *                              Используется в случаях, когда SQL-запрос содержит JOIN или
     *                              вложенные подзапросы
     * @return $this
     */
    public function cacheMe(?array $involvedTables = null): self
    {
        if ($involvedTables) {
            $this->involvedTables = $involvedTables;
        }

        $this->cacheEnabled = true;

        return $this;
    }

    /**
     * Создание таблицы $table на основе данных полей $fields
     *
     * @param string $table  Название создаваемой таблицы
     * @param array $fields Названия создаваемых полей и описания их типа
     * @return bool|mysqli_result
     */
    public function create(string $table, array $fields)
    {
        $sqlFields = [];

        foreach ($fields as $key => $value) {
            if (!isset($value['sql']) || ($value['sql'] === '')) {
                // Пропускаем поля, которые не нужно создавать в БД
                continue;
            }
            $sqlFields[] = "`$key` {$value['sql']} COMMENT '{$value['label']}'";
        }

        $sql = "CREATE TABLE `$table` (" . implode(',', $sqlFields) . ') DEFAULT CHARSET=utf8';

        return $this->query($sql);
    }

    /**
     * Удаление одной или нескольких строк
     *
     * Пример использования:
     *     $db->delete($table)->where($sql, $params)->exec();
     * ВНИМАНИЕ: в результате выполнения этого метода сбрасывается кэш БД
     *
     * @param string $table Таблица, в которой будут удаляться строки
     * @return $this
     */
    public function delete(string $table): self
    {
        // Очищаем where, если он был задан ранее.
        // Записываем название таблицы для DELETE

        $this->clearQueryAttributes();
        $this->deleteTableName = $table;

        return $this;
    }

    /**
     * Очистка параметров текущего update/delete запроса
     */
    protected function clearQueryAttributes(): void
    {
        $this->deleteTableName = '';
        $this->updateTableName = '';
        $this->whereParams = [];
        $this->updateValues = [];
        unset($this->involvedTables);
    }

    /**
     * Выполняет сформированный update/delete-запрос
     *
     * @return bool Либо флаг успешности выполнения запроса
     */
    public function exec(): bool
    {
        if (!$this->updateTableName && !$this->deleteTableName) {
            Util::addError('Попытка вызова exec() без update() или delete().');
            return false;
        }

        $tag = $this->updateTableName ?: $this->deleteTableName;
        $sql = $this->updateTableName ? $this->getUpdateQuery() : $this->getDeleteQuery();

        $this->clearCache($tag);
        if ($this->query($sql)) {
            // Если запрос выполнился успешно, то очистить все заданные параметры запроса, иначе не затирать их,
            // чтобы получить неправильный запрос при повторном вызове exec()
            $this->clearQueryAttributes();
        }

        return true;
    }

    /**
     * Получение сформированного sql-запрос
     *
     * @return string Sql-запрос
     */
    public function getSql(): string
    {
        if (!$this->updateTableName && !$this->deleteTableName) {
            Util::addError('Попытка вызова exec() без update() или delete().');
            return false;
        }

        return $this->updateTableName ? $this->getUpdateQuery() : $this->getDeleteQuery();
    }

    /**
     * Возвращает SQL-запрос для операции update() на основе значений, заданных с использованием set() и where()
     *
     * @return string UPDATE запрос
     */
    protected function getUpdateQuery(): string
    {
        $values = [];

        foreach ($this->updateValues as $column => $value) {
            $column = '`' . $this->escape_string($column) . '`';
            if ($value === null) {
                $value = 'NULL';
            } elseif (is_bool($value)) {
                $value = (int)$value;
            } else {
                $value = "'" . $this->escape_string($value) . "'";
            }
            $values[] = "$column = $value";
        }

        $values = implode(', ', $values);
        $this->updateTableName = '`' . $this->escape_string($this->updateTableName) . '`';
        $where = '';

        if ($this->whereQuery) {
            $where = 'WHERE ' . $this->prepareSql($this->whereQuery, $this->whereParams);
        }

        return 'UPDATE ' . $this->updateTableName . ' SET ' . $values . ' ' . $where . ';';
    }

    /**
     * Подготовка запроса к выполнению
     *
     * Все значения из $params экранируются и подставляются в $sql на место
     * плейсхолдеров `:fieldName`, имена таблиц подставляются на место
     * плейсхолдера &table
     *
     * @param string $sql    Необработанный SQL-запрос
     * @param array|null $params Массив пар поле-значение, участвующих в запросе $sql
     * @param array|null $fields Имена таблиц участвующих в запросе $sql
     * @return string Подготовленный SQL-запрос
     */
    protected function prepareSql(string $sql, ?array $params = null, ?array $fields = null): string
    {
        if (is_array($params)) {
            uksort($params, static function ($a, $b) {return mb_strlen($a) < mb_strlen($b) ? 1 : -1;});
            foreach ($params as $key => $value) {
                if ($value === null) {
                    $value = 'NULL';
                } else {
                    $value = "'" . $this->escape_string($value) . "'";
                }
                $sql = str_replace(":$key", $value, $sql);
            }
        }

        if (is_array($fields)) {
            uksort($fields, static function ($a, $b) {return mb_strlen($a) < mb_strlen($b) ? 1 : -1;});
            foreach ($fields as $key => $value) {
                $field = $this->escape_string($value);
                $sql = str_replace("&$key", "`$field`", $sql);
            }
        }

        return $sql;
    }

    /**
     * Возвращает SQL-запрос для операции delete() на основе значений, заданных с использованием where()
     *
     * @return string DELETE запрос
     */
    protected function getDeleteQuery(): string
    {
        $this->deleteTableName = '`' . $this->escape_string($this->deleteTableName) . '`';

        if ($this->whereQuery === '') {
            throw new RuntimeException('Запрос DELETE без WHERE удалит все данные таблицы');
        }

        return 'DELETE FROM ' . $this->deleteTableName
            . ' WHERE ' . $this->prepareSql($this->whereQuery, $this->whereParams) . ';';
    }

    /**
     * Очистка кэша запросов, связанных с таблицей $table
     *
     * @param string $table Название таблицы, для запросов из которой нужно очистить кэш
     */
    public function clearCache(string $table): void
    {
        if (isset($this->cache)) {
            $this->cache->deleteByTag($table);
        }
    }

    /**
     * Вставка новой строки в таблицу
     *
     * Пример использования:
     *     $params = array(
     *       'firstField' => 'firstValue',
     *       'secondField' => 'secondValue',
     *     )
     *     $id = $db->insert('table', $params);
     * ВНИМАНИЕ: в результате выполнения этого метода сбрасывается кэш БД
     *
     * @param string $table  Таблица, в которую необходимо вставить строку
     * @param array $params Значения полей для вставки строки
     * @return int ID вставленной строки
     */
    public function insert(string $table, array $params): int
    {
        $this->clearCache($table);
        $values = [];
        $columns = [];

        foreach ($params as $column => $value) {
            $columns[] = '`' . $this->escape_string($column) . '`';
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (is_bool($value) || is_int($value)) {
                $values[] = (int)$this->escape_string($value);
            } else {
                $values[] = "'" . $this->escape_string($value) . "'";
            }
        }

        $columns = implode(', ', $columns);
        $values = implode(', ', $values);
        $table = $this->escape_string($table);
        /** @noinspection SqlResolve */
        $sql = 'INSERT INTO `' . $table . '` (' . $columns . ') VALUES (' . $values . ');';
        $this->query($sql);

        return $this->insert_id;
    }

    /**
     * Вставка новых строк в таблицу
     *
     * Пример использования:
     *     $params = array(
     *       '0' => array(
     *              'firstField' => 'firstValue',
     *              'secondField'=> 'secondValue,
     *          ),
     *       '1' => array(
     *              'firstField' => 'firstValue',
     *              'secondField'=> 'secondValue,
     *          ),
     *      )
     *     $id = $db->insert('table', $params);
     * ВНИМАНИЕ: в результате выполнения этого метода сбрасывается кэш БД
     *
     * @param string $table  Таблица, в которую необходимо вставить строку
     * @param array $params Значения полей для вставки строки
     * @return int Количество затронутых строк
     */
    public function insertMultiple(string $table, array $params): int
    {
        $this->clearCache($table);
        $columns = [];
        $values = $columns;

        $cols = array_keys(reset($params));
        // Получаем название полей
        foreach ($cols as $column) {
            $columns[] = '`' . $this->escape_string($column) . '`';
        }

        $data = [];
        foreach ($params as $item) {
            foreach ($item as $value) {
                // Добавляемые значения для 1 строки
                $data[] = "'" . $this->escape_string($value) . "'";
            }
            if (!empty($data)) {
                // Массив всех добавляемых строк
                $values[] = '(' . implode(', ', $data) . ')';
                unset($data);
            }
        }

        $columns = implode(', ', $columns);
        $values = implode(', ', $values);
        $table = $this->escape_string($table);
        /** @noinspection SqlResolve */
        $sql = 'INSERT INTO `' . $table . '` (' . $columns . ') VALUES ' . $values . ';';
        $this->query($sql);

        return $this->affected_rows;
    }

    /**
     * Выборка строк из БД по заданному запросу $sql
     *
     * Пример использования:
     *     $par = array('time' => time(), active => true);
     *     $fields = array('table' => 'full_table_name');
     *     $rows = $db->select('SELECT * FROM &table WHERE time < :time AND is_active = :active', $par, $fields);
     *
     * @param string $sql    SELECT-запрос
     * @param array|null $params Параметров, которые будут экранированы и закавычены как параметры
     * @param array|null $fields Названий полей и таблиц, которые будут экранированы и закавычены как названия полей
     * @return array Ассоциативный массив сделанной выборки из БД
     * @noinspection MultipleReturnStatementsInspection
     */
    public function select(string $sql, array $params = null, ?array $fields = null): array
    {
        $sql = $this->prepareSql($sql, $params, $fields);

        if (!$this->cacheEnabled || !isset($this->cache)) {
            // Если кэширование не включено, то выполняем запрос и возвращаем результат в виде ассоциативного массива
            $result = $this->query($sql);
            if ($result === false) {
                return [];
            }

            if (method_exists('mysqli_result', 'fetch_all')) {
                $res = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                // Если у класса mysqli_result нет метода fetch_all (не подключен mysqlnd),
                // то считываем в массив построчно с помощью fetch_array
                for ($res = []; $tmp = $result->fetch_array(MYSQLI_ASSOC);) {
                    $res[] = $tmp;
                }
            }

            return $res;
        }

        $this->cacheEnabled = false; // Т.к. кэширование включается только для одного запроса

        $cacheKey = $this->prepareCacheKey($sql);

        if ($cachedResult = $this->cache->getWithTags($cacheKey)) {
            return $cachedResult;
        }

        if (method_exists('mysqli_result', 'fetch_all')) {
            $queryResult = $this->query($sql)->fetch_all(MYSQLI_ASSOC);
        } else {
            // Если у класса mysqli_result нет метода fetch_all (не подключен mysqlnd),
            // то считываем в массив построчно с помощью fetch_array
            $result = $this->query($sql);
            for ($queryResult = []; $tmp = $result->fetch_array(MYSQLI_ASSOC);) {
                $queryResult[] = $tmp;
            }
        }

        $cacheTags = $this->prepareCacheTags($sql);
        $this->cache->setWithTags($cacheKey, $queryResult, false, $cacheTags);

        return $queryResult;
    }

    /**
     * Возвращает ключ для кеширования запроса
     *
     * @param $query string SQL-запрос
     * @return string Md5 от запроса, переведенного в нижний регистр
     */
    protected function prepareCacheKey(string $query): string
    {
        return md5(strtolower($this->dbName . $query));
    }

    /**
     * Возвращает массив тегов, полученных на основе SQL-запроса
     *
     * Запрос разбирается в случае если теги (имена таблиц) явно не указаны при вызове cacheMe().
     *
     * @param $query string SQL-запрос
     * @return array
     */
    protected function prepareCacheTags(string $query): array
    {
        if ($this->involvedTables) {
            return $this->involvedTables;
        }

        // Запрос переводится в нижний регистр, после чего из него вырезаются
        // все символы до последнего ключевого слова FROM
        // и все символы начиная со следующего возможного ключевого слова

        $query = strtolower($query);
        $query = preg_replace('/^(.|\n)*from\s+/i', '', $query);
        $pattern = '/\s+(join\s+|left\s+|right\s+|where\s+|group\s+by|having\s+|order\s+by|limit\s+)(.|\n)*$/i';
        $query = preg_replace($pattern, '', $query);

        // Полученное значение разбивается на массив и очищается от кавычек и псевдонимов

        if (strpos($query, ',') !== false) {
            $queryArr = explode(',', $query);
        } else {
            $queryArr = [$query];
        }

        foreach ($queryArr as $key => $value) {
            $value = str_replace(['\'', '"', '`'], '', $value);
            $asPosition = strpos($value, ' as ');

            if ($asPosition !== false) {
                $value = substr($value, 0, $asPosition);
            }

            $value = trim($value);
            $queryArr[$key] = $value;
        }

        return array_unique($queryArr);
    }

    /**
     * В формируемый update-запрос добавляет значения полей для вставки
     *
     * @param array $values Названия и значения полей для вставки строки в таблицу
     * @return $this Db
     */
    public function set(array $values): self
    {
        $this->updateValues = $values;
        return $this;
    }

    /**
     * Обновление одной или нескольких строк
     *
     * Пример использования:
     *     $db->update($table)->set($values)->where($sql, $params)->exec();
     * ВНИМАНИЕ: в результате выполнения этого метода сбрасывается кэш БД
     *
     * @param string $table Таблица, в которой будут обновляться строки
     * @return $this
     */
    public function update(string $table): self
    {
        // Очищаем set и where, если они были заданы ранее.
        // Записываем название таблицы для UPDATE

        $this->clearQueryAttributes();
        $this->updateTableName = $table;

        return $this;
    }

    /**
     * В формируемый update/delete-запрос добавляет where-условие
     *
     * Пример использования:
     *     $par = array('active' = 1);
     *     $db->delete('table_name')->where('is_active = :active', $par)->exec();
     *
     * @param string $sql    Строка where-условия
     * @param array $params Параметры, используемые в строке where-условия
     * @return $this
     */
    public function where(string $sql, array $params = []): self
    {
        $this->whereQuery = $sql;
        $this->whereParams = $params;

        return $this;
    }

    /**
     * Установка параметра логирования ошибок
     *
     * @param bool $bool
     */
    public function setLogError(bool $bool): void
    {
        $this->logError = $bool;
    }
}
