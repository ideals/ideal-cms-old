<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Addon\SiteMap;

use Ideal\Addon\AbstractSiteModel;
use Ideal\Core\Config;
use Ideal\Core\Util;
use Ideal\Structure\Part\Site\Model;

/**
 * Класс построения html-карты сайта на основании структуры БД
 *
 */
class SiteModel extends AbstractSiteModel
{

    /** @var array Массив правил для запрещения отображения ссылок в карте сайта */
    protected array $disallow = [];

    /**
     * Извлечение настроек карты сайта из своей таблицы,
     * построение карты сайта и преобразование её в html-формат
     *
     * @return array
     */
    public function getPageData(): array
    {
        $this->setPageDataByPrevStructure($this->prevStructure);

        $this->pageData['disallow'] = str_replace("\r\n", "\n", $this->pageData['disallow']);
        $this->disallow = explode("\n", $this->pageData['disallow']);

        $this->pageData['content'] = '';

        $mode = explode('\\', get_class($this->parentModel));
        if ($mode !== false && isset($mode[3]) && $mode[3] === 'Site') {
            // Для фронтенда к контенту добавляется карта сайта в виде ul-списка разделов
            $list = $this->getList(1); // считываем из БД все открытые разделы
            $this->pageData['content'] = $this->createSiteMap($list); // строим html-код карты сайта
        }

        return $this->pageData;
    }

    /**
     * Построение карты сайта в виде дерева
     *
     * @param int|null $page Не используется
     * @return array
     */
    public function getList(int $page = null): array
    {
        $config = Config::getInstance();

        // Определение стартовой структуры и начать считывание с неё
        $structure = $config->structures[0];
        $className = Util::getClassName($structure['structure'], 'Structure') . '\\Site\\Model';
        /** @var $startStructure Model */
        $startStructure = new $className($structure['ID']);
        $elements = $startStructure->getStructureElements();
        $path = [$structure];

        return $this->recursive($path, $elements);
    }

    /**
     * Рекурсивный метод построения дерева карты сайта
     *
     * @param $path
     * @param $elements
     * @return array
     */
    protected function recursive($path, $elements): array
    {
        if (empty($elements)) {
            return [];
        }

        $config = Config::getInstance();
        $end = end($path);
        $fullPath = $path;
        $lvl = 0;
        $newElements = [];
        // Проходился по всем внутренним структурам и, если вложены другие структуры, получаем и их элементы
        foreach ($elements as $element) {
            $newElements[] = $element;

            // Строим полный путь до каждого элемента структуры
            if (isset($element['lvl'])) {
                if ($element['lvl'] <= $lvl) {
                    // Срезаем элементы пути предыдущего элемента
                    $c = $lvl - $element['lvl'] + 1;
                    $fullPath = array_slice($fullPath, 0, -$c);
                }
                $lvl = $element['lvl'];
            } elseif (count($fullPath) > count($path)) {
                // Если структура без вложенных элементов, то каждый раз заменяем последний элемент
                array_pop($fullPath);
            }
            $fullPath[] = $element;

            if (!isset($element['structure']) || ($element['structure'] === $end['structure'])) {
                continue;
            }

            // Если структуры предпоследнего $end и последнего $element элементов не совпадают,
            // считываем элементы вложенной структуры
            $structure = $config->getStructureByName($end['structure']);
            $className = Util::getClassName($element['structure'], 'Structure') . '\\Site\\Model';
            $prevStructure = $structure['ID'] . '-' . $element['ID'];
            $nextStructure = new $className($prevStructure);
            $nextStructure->setPath($fullPath);
            $nextStructure->setPrevStructure($prevStructure);
            // Считываем элементы из вложенной структуры
            $addElements = $nextStructure->getStructureElements();
            // Рекурсивно читаем вложенные элементы из вложенной структуры
            $addElements = $this->recursive($fullPath, $addElements);

            // Увеличиваем уровень вложенности на считанных элементах
            foreach ($addElements as $v) {
                if (isset($v['lvl'])) {
                    $v['lvl'] += $element['lvl'];
                } else {
                    $v['lvl'] = $element['lvl'] + 1;
                }
                // Получившийся список добавляем в наш массив новых элементов
                $newElements[] = $v;
            }
        }

        return $newElements;
    }

    /**
     * Построение html-карты сайта на основе древовидного списка
     *
     * @param array $list Древовидный список
     * @return string Html-код списка ссылок карты сайта
     */
    public function createSiteMap(array $list): string
    {
        $str = '';
        $lvl = 0;
        foreach ($list as $v) {
            $v['lvl'] = (int)$v['lvl'];
            if ($v['lvl'] > $lvl) {
                $str .= "\n<ul class=\"site-map\">\n";
            } elseif ($v['lvl'] === $lvl) {
                $str .= "</li>\n";
            } elseif ($v['lvl'] < $lvl) {
                // Если двойной или тройной выход добавляем соответствующий мультипликатор
                $c = $lvl - $v['lvl'];
                $str .= str_repeat("</li>\n</ul>\n</li>\n", $c);
            }

            if (empty($v['link']) || (isset($v['is_skip']) && ((int)$v['is_skip'] === 1) && ($v['url'] === '---'))) {
                // Если у элемента нет ссылки, или у него прописан is_skip=1 и url='--', то не выводим ссылку
                $str .= '<li>' . $v['name'];
            } else {
                // Проходимся по массиву регулярных выражений. Если array_reduce вернёт саму ссылку,
                // то подходящего правила в disallow не нашлось и можно эту ссылку добавлять в карту сайта
                $tmp = $this->disallow;

                $link = array_reduce($tmp, static function ($res, $rule) {
                    if (!empty($rule)) {
                        if ($res === 1 || preg_match($rule, $res)) {
                            return 1;
                        }
                    }
                    return $res;
                }, $v['link']);
                if ($v['link'] !== $link) {
                    // Сработало одно из регулярных выражений, значит ссылку нужно исключить
                    continue;
                }
                $href = strpos($v['link'], 'href=') === false ? 'href="' . $v['link'] . '"' : $v['link'];
                $href = $href === 'href=""' ? '' : $href;
                $str .= '<li><a ' . $href . '>' . $v['name'] . '</a>';
            }
            $lvl = $v['lvl'];
        }
        $str .= "</li>\n</ul>\n";
        return $str;
    }
}
