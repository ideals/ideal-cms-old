<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core;

/**
 * Класс предназначен для генерации sql запросов фильтрации и сортировки
 *
 */
abstract class Filter
{

    /** @var array Массив для хранения параметров фильтрации и сортировки */
    protected array $params = [];

    /** @var string Строка содержащая информацию о фильтрации в запросе */
    protected string $where = '';

    /** @var string Строка содержащая информацию о сортировке в запросе */
    protected string $orderBy = '';

    /**
     * Генерирует запрос для получения списка элементов
     */
    abstract public function getSql();

    /**
     * Генерирует запрос для получения количества элементов в списке
     */
    abstract public function getSqlCount();

    /**
     * Генерирует where часть запроса и сохраняет её в свойство 'where'
     */
    abstract protected function generateWhere();

    /**
     * Устанавливает значения параметров фильтрации и сортировки
     *
     * @param $params array Список параметров для фильтрации
     */
    public function setParams(array $params): void
    {
        $db = Db::getInstance();
        foreach ($params as $key => $value) {
            $params[$key] = $db->escape_string($value);
        }
        $this->params = $params;
    }

    /**
     * Возвращает значения параметров фильтрации и сортировки
     *
     * @return array Параметры фильтрации и сортировки
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Генерирует order by часть запроса
     */
    protected function generateOrderBy(): void
    {
        $this->orderBy = '';
    }
}
