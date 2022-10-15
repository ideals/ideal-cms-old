<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core\Site;

use Ideal\Core;
use Ideal\Field;

abstract class Model extends Core\Model
{

    public array $metaTags = [
        'robots' => 'index, follow'
    ];

    /** @var bool Нужно ли удалять заголовок h1 из текста */
    protected bool $isExtractHeader = true;

    /**
     * @param array $path
     * @param array $url
     *
     * @return $this
     */
    abstract public function detectPageByUrl(array $path, array $url);

    /**
     * Заглушка для метода, возвращающего список вложенных элементов выбранного элемента структуры
     *
     * Этот метод используется в построении на основе БД html-карты сайта
     *
     * @return array Список вложенных элементов
     */
    public function getStructureElements(): array
    {
        return [];
    }

    public function getBreadCrumbs()
    {
        $path = $this->path;
        $path[0]['name'] = $path[0]['startName'];

        if (isset($this->path[1]['url']) && ($this->path[1]['url'] === '/') && count($path) === 2) {
            // На главной странице хлебные крошки отображать не надо
            return '';
        }

        // Отображение хлебных крошек
        $pars = [];
        $breadCrumbs = [];
        $url = new Field\Url\Model();
        foreach ($path as $v) {
            if (isset($v['is_skip'], $v['is_not_menu']) && $v['is_skip'] && $v['is_not_menu']) {
                continue;
            }
            $url->setParentUrl($pars);
            $link = $url->getUrl($v);
            $pars[] = $v;
            if ($link === '/') {
                if ($v['url'] === '') {
                    $breadCrumbs[] = [
                        'link' => $link,
                        'name' => $v['startName']
                    ];
                } else {
                    // В случае, если путь строится для главной страницы - дублирование не нужно
                    /** @noinspection PhpUnnecessaryStopStatementInspection */
                    continue;
                }
            } else {
                $breadCrumbs[] = [
                    'link' => $link,
                    'name' => $v['name']
                ];
            }
        }
        return $breadCrumbs;
    }

    public function getHeader()
    {
        $header = '';
        // Если есть шаблон с контентом, пытаемся из него извлечь заголовок H1
        if (isset($this->pageData['content']) && !empty($this->pageData['content'])) {
            [$header, $text] = $this->extractHeader($this->pageData['content']);
            $this->pageData['content'] = $text;
        } elseif (!empty($this->pageData['addon'])) {
            // Последовательно пытаемся получить заголовок из всех аддонов до первого найденного
            if (isset($this->pageData['addons'])) {
                foreach ($this->pageData['addons'] as $i => $iValue) {
                    if (isset($iValue['content']) && $iValue['content'] !== '') {
                        [$header, $text] = $this->extractHeader($iValue['content']);
                        if (!empty($header)) {
                            $this->pageData['addons'][$i]['content'] = $text;
                            break;
                        }
                    }
                }
            }
        }

        if ($header === '' && isset($this->pageData['name'])) {
            // Если заголовка H1 в тексте нет, берём его из названия name
            $header = $this->pageData['name'];
        }
        return $header;
    }

    public function extractHeader($text): array
    {
        $header = '';
        if (preg_match('/<h1.*>\s*(.*)<\/h1>/isU', $text, $headerArray)) {
            if ($this->isExtractHeader) {
                $text = preg_replace('/<h1.*>\s*(.*)<\/h1>/isU', '', $text, 1);
            }
            $header = $headerArray[1];
        }
        return [$header, $text];
    }

    /**
     * Формирование html-кода мета-тегов страницы
     *
     * @param bool $xhtml XHTML или не XHTML формат кода
     * @return string Мета-теги страницы в html-формате
     */
    public function getMetaTags(bool $xhtml = false): string
    {
        $meta = '';
        $xhtmlChar = $xhtml ? '/' : '';
        $end = end($this->path);

        if (isset($end['description']) && $end['description'] !== '' && (!isset($this->pageNum) || $this->pageNum === 1)) {
            $meta .= '<meta name="description" content="'
                . str_replace('"', '&quot;', $end['description'])
                . '" ' . $xhtmlChar . '>';
        }

        if (isset($end['keywords']) && $end['keywords'] !== '' && (!isset($this->pageNum) || $this->pageNum === 1)) {
            $meta .= '<meta name="keywords" content="'
                . str_replace('"', '&quot;', $end['keywords'])
                . '" ' . $xhtmlChar . '>';
        }

        foreach ($this->metaTags as $tag => $value) {
            $meta .= '<meta name="' . $tag . '" content="'
                . $value . '" ' . $xhtmlChar . '>';
        }

        return $meta;
    }

    /**
     * Получение <title> для страницы
     *
     * Title может быть либо задан через параметр title в $this->pageDate, а если title отсутствует или пуст,
     * то title генерируется из параметра name.
     * Кроме того, в случае, если запрашивается не первая страница листалки (новости, статьи и т.п.), то
     * этот метод добавляет суффикс листалки с указанием номера страницы
     *
     * @return string Title для страницы
     */
    public function getTitle(): string
    {
        $end = $this->pageData;
        $concat = ($this->pageNum > 1) ? str_replace('[N]', $this->pageNum, $this->pageNumTitle) : '';
        if (isset($end['title']) && $end['title'] !== '') {
            return $end['title'] . $concat;
        }

        return $end['name'] . $concat;
    }

    /**
     * Получение канонической ссылки для запрошенной страницы
     *
     * @return string Каноническая ссылка для запрошенной страницы
     */
    public function getCanonical(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] === 443) ? 'https://' : 'http://';
        [$path] = explode('?', $_SERVER['REQUEST_URI']);
        $canonical = "$protocol{$_SERVER['HTTP_HOST']}$path";
        $config = Core\Config::getInstance();
        $indexedOptions = explode(',', $config->cms['indexedOptions']);
        $params = array_intersect_key($_GET, array_flip($indexedOptions));
        if ($params) {
            $canonical .= '?' . http_build_query($params);
        }
        return $canonical;
    }
}
