<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\Cid;

use Ideal\Field;

/**
 * Модель для работы с сидами — основным элементом построения древовидной структуры Materialized Path.
 *
 */
class Model
{

    /** @var int Количество цифр (разрядов) на одном уровне вложенности */
    private int $digits;

    /** @var int Количество уровней вложенности в cid */
    private int $levels;

    /**
     * Устанавливает количество уровней вложенности и количество разрядов на одном уровне вложенности
     *
     * @param int $levels Количество уровней вложенности в cid
     * @param int $digits Количество цифр (разрядов) на одном уровне вложенности
     */
    public function __construct(int $levels, int $digits)
    {
        $this->levels = $levels;
        $this->digits = $digits;
    }

    /**
     * @param array $menu
     * @param array $path
     * @return array
     */
    public function buildTree(array &$menu, array $path): array
    {
        $url = new Field\Url\Model();
        $url->setParentUrl($path);

        // Записываем в массив первый элемент
        $categoryList = [
            array_shift($menu)
        ];
        $categoryList[0]['link'] = $url->getUrl($categoryList[0]);

        $prev = $categoryList[0]['lvl'];

        while (count($menu) !== 0) {
            $m = reset($menu);
            if ($m['lvl'] === $prev) {
                $m['link'] = $url->getUrl($m);
                $categoryList[] = $m;
                array_shift($menu);
            } elseif ($m['lvl'] > $prev) {
                $end = end($categoryList);
                $key = key($categoryList);
                $inPath = array_merge($path, [$end]);
                $categoryList[$key]['subCategoryList'] = $this->buildTree($menu, $inPath);
            } else {
                return $categoryList;
            }
        }
        return $categoryList;
    }

    /**
     * Сплющиваем дерево в одноуровневый массив
     *
     * @param array $tree Дерево с вложенными ветками
     * @return array Плоский массив всех элементов дерева
     */
    public function plainTree(array $tree): array
    {
        $list = [];
        foreach ($tree as $v) {
            if (isset($v['subCategoryList'])) {
                $arr = $this->plainTree($v['subCategoryList']);
                unset($v['subCategoryList']);
                $list[] = $v;
                foreach ($arr as $item) {
                    $list[] = $item;
                }
            } else {
                $list[] = $v;
            }
        }
        return $list;
    }

    /**
     * Возвращает родительский cid для указанного уровня $lvl
     *
     * @param string $cid     Cid из которого надо извлечь родителя
     * @param int $lvl     Уровень для которого надо извлечь родителя
     * @param bool $fullCid Нужно возвращать полный cid или только начальную часть (для поиска)
     * @return string Родительский cid
     */
    public function getCidByLevel(string $cid, int $lvl, bool $fullCid = true): string
    {
        $parentCid = substr($cid, 0, ($lvl * $this->digits));
        if ($fullCid) {
            $parentCid = $this->reconstruct($parentCid);
        }
        return $parentCid;
    }

    /**
     * Добивает $cid нулями в конце, до превращения его в полный cid
     *
     * @param string $cid Строка с cid адресом, у которого не хватает нулей
     * @return string Сформированный полноценный cid-адрес
     */
    public function reconstruct(string $cid): string
    {
        // Вставляем нужное количество нулей после строки $num
        return str_pad($cid, $this->levels * $this->digits, '0');
    }

    /**
     * Возвращает массив с родительскими сидами для заданного cid
     *
     * @param string $cid Сид для которого нужно определить родительские сиды
     * @return array Массив родительских сидов
     */
    public function getParents(string $cid): array
    {
        $parents = [];
        $parentCid = '';
        $blocks = str_split($cid, $this->digits);
        foreach ($blocks as $v) {
            if ((int)$v === 0) {
                break;
            }
            $parentCid .= $v;
            $parents[] = $this->reconstruct($parentCid);
        }
        array_pop($parents); // убираем последний элемент
        return $parents;
    }

    /**
     * Изменение позиции $oldCid на указанном уровне $lvl на указанное значение $newSegment
     *
     * @param string $oldCid        Полный cid, который нужно переместить
     * @param int $newCidSegment Новое значение позиции
     * @param int $lvl           Уровень на котором меняется позиция
     * @return string
     */
    public function moveCid(string $oldCid, int $newCidSegment, int $lvl): string
    {
        // Определяем старую позицию на указанном уровне
        $oldCidSegment = $this->getBlock($oldCid, $lvl);

        $parentCid = substr($oldCid, 0, (($lvl - 1) * $this->digits));

        $oldCidPart = $parentCid . $this->numToCid($oldCidSegment);
        $newCidPart = $parentCid . $this->numToCid($newCidSegment);
        $update[$oldCidPart] = $newCidPart;

        // Определяем реальное значение сегмента в новом cid
        // если cid становится больше, то новое значение уменьшается на единицу,
        // если меньше, то новое значение остаётся прежним
        if ($newCidSegment > $oldCidSegment) {
            for ($i = (int)$oldCidSegment + 1; $i < $newCidSegment + 1; $i++) {
                $oldCidPart = $parentCid . $this->numToCid($i);
                $newCidPart = $parentCid . $this->numToCid($i - 1);
                $update[$oldCidPart] = $newCidPart;
            }
        } else {
            for ($i = $newCidSegment; $i < $oldCidSegment; $i++) {
                $oldCidPart = $parentCid . $this->numToCid($i);
                $newCidPart = $parentCid . $this->numToCid($i + 1);
                $update[$oldCidPart] = $newCidPart;
            }
        }

        $tailPos = $lvl * $this->digits + 1; // Начало хвоста — неизменяемой части cid, идущей после изменяемого уровня
        $_sql = /** @lang text */
            'UPDATE {{ table }} SET cid = CASE';
        $or = '';
        $where = '';
        foreach ($update as $old => $new) {
            $_sql .= "\nWHEN cid LIKE '$old%' THEN CONCAT('$new', substring(cid, $tailPos))";
            $where .= $or . " cid LIKE '$old%'";
            $or = ' OR';
        }
        $_sql .= "\n ELSE cid END WHERE " . $where . ';';
        // На основании массива $update составляем список запросов для обновления cid'ов
        return $_sql;
    }

    /**
     * Изменение значения cid-блока на уровне $lvl на указанное значение $n
     *
     * Если ставится флаг $new, то все значения в $cid, после уровня $lvl
     * обнуляются
     *
     * @param string $cid Cid для изменения
     * @param int $lvl Уровень, на котором нужно поменять значение
     * @param int $n   Число, которое надо прибавить, к тому, что есть
     * @param bool $new Флаг обнуления значений после указанного уровня
     * @return string Изменённый cid
     */
    public function setBlock(string $cid, int $lvl, int $n, bool $new = false): string
    {
        // Определение неизменяемых границ
        $start = ($lvl - 1) * $this->digits;
        $end = $start + $this->digits;

        // Приведение $cid к стандартному формату, если он не в формате
        $cid = $this->reconstruct($cid);

        // Выцепление неизменяемых частей
        $startBlock = substr($cid, 0, $start);
        $endBlock = substr($cid, $end);

        // Изменение блока
        $block = $this->getBlock($cid, $lvl, $n);

        if ($new) {
            $endBlock = str_repeat('0', strlen($endBlock));
        }

        // Составление изменённого cid адреса
        return $startBlock . $block . $endBlock;
    }

    /**
     * Определение cid-блока на уровне $lvl и прибавление к нему $n
     *
     * @param string $cid Исходный cid-адрес
     * @param int $lvl Уровень, на котором надо поменять число
     * @param string $n Число, которое надо прибавить, к тому, что есть
     * @return string Возвращает только блок из cid на указанном уровне
     */
    public function getBlock(string $cid, int $lvl, string $n = '0'): string
    {
        $current = ($lvl - 1) * $this->digits; // граница до несущей части адреса
        $num = substr($cid, $current, $this->digits); // выцепляем номер

        // Изменяем на нужное число
        if ($n[0] === '+') {
            $num += (int)substr($n, 1);
        } elseif ($n[0] === '-') {
            $num -= (int)substr($n, 1);
        } elseif ($n !== '0') {
            $num = $n;
        }

        // конвертация числа в блок cid адреса
        return $this->numToCid($num);
    }

    /**
     * Конвертация числа в блок cid адреса
     *
     * Просто добавляет нули в начало переданного числа, чтобы сделанная
     * строка соответствовала длине cid-блока
     *
     * @param int $num Число, которое нужно превратить в блок cid-адреса
     * @return string Сформированный полноценный cid-адрес
     */
    public function numToCid(int $num): string
    {
        // TODO сделать сообщение об ошибке, если число больше допустимого

        // Вставляем перед числом нужное кол-во нулей
        return str_pad($num, $this->digits, '0', STR_PAD_LEFT);
    }
}
