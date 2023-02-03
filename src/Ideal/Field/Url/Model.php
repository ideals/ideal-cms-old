<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\Url;

use Exception;
use Ideal\Core\Config;
use Ideal\Core\PluginBroker;

/**
 * Модель для работы с основными задачами по URL
 *
 * * Установка родительского URL
 * * Определение URL элемента
 * * Транслитерация URL
 * * Транслитерация файловых имён
 *
 */
class Model
{
    // TODO сделать возможность определять url Не только по полю url

    /** @var string Родительский URL, используемый для построения URL элементов на этом уровне */
    protected string $parentUrl = '';

    /**
     * Удаление символов, неприменимых в URL
     *
     * @param string $nm Исходная ссылка
     * @return string Преобразованная ссылка
     */
    public static function translitUrl(string $nm): string
    {
        $nm = self::translit($nm);
        $nm = mb_strtolower($nm);
        $arr = [
            '@' => '',
            '$' => '',
            '^' => '',
            '+' => '',
            '|' => '',
            '{' => '',
            '}' => '',
            '>' => '',
            '<' => '',
            ':' => '',
            ';' => '',
            '[' => '',
            ']' => '',
            '\\' => '',
            '*' => '',
            '(' => '',
            ')' => '',
            '!' => '',
            '#' => 'N',
            '—' => '',
            '/' => '-',
            '«' => '',
            '»' => '',
            '.' => '',
            '№' => 'N',
            '"' => '',
            "'" => '',
            '?' => '',
            ' ' => '-',
            '&' => '',
            ',' => '',
            '%' => ''
        ];
        return strtr($nm, $arr);
    }

    /**
     * Транслитерация русских букв в латинские
     *
     * @param string $nm Исходная строка
     *
     * @return string Преобразованная строка
     */
    public static function translit(string $nm): string
    {
        $arr = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'j',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'shh',
            'ы' => 'y',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            'ь' => '',
            'ъ' => '',
            'А' => 'a',
            'Б' => 'b',
            'В' => 'v',
            'Г' => 'g',
            'Д' => 'd',
            'Е' => 'e',
            'Ё' => 'e',
            'Ж' => 'zh',
            'З' => 'z',
            'И' => 'i',
            'Й' => 'j',
            'К' => 'k',
            'Л' => 'l',
            'М' => 'm',
            'Н' => 'n',
            'О' => 'o',
            'П' => 'p',
            'Р' => 'r',
            'С' => 's',
            'Т' => 't',
            'У' => 'u',
            'Ф' => 'f',
            'Х' => 'h',
            'Ц' => 'c',
            'Ч' => 'ch',
            'Ш' => 'sh',
            'Щ' => 'shh',
            'Ы' => 'y',
            'Э' => 'e',
            'Ю' => 'yu',
            'Я' => 'ya',
            'Ь' => '',
            'Ъ' => ''
        ];
        return strtr($nm, $arr);
    }

    /**
     * Отрезает стандартный суффикс от ссылки
     *
     * @param $link
     * @return string
     */
    public function cutSuffix($link): string
    {
        $config = Config::getInstance();
        return substr($link, 0, -strlen($config->urlSuffix));
    }

    /**
     * Получение url для элемента $lastPart на основании ранее установленного пути или префикса $parentUrl
     *
     * @param array $lastPart Массив с основными данными об элементе
     * @return string Сгенерированный URL этого элемента
     */
    public function getUrl(array $lastPart): string
    {
        return self::getUrlWithPrefix($lastPart, $this->parentUrl);
    }

    /**
     * Получение url для элемента $lastPart на основании ранее установленного пути или префикса $parentUrl
     *
     * Метод генерирует событие onGetUrl, которое могут перехватывать плагины ддя создания специальных правил
     * получения URL.
     *
     * @param array  $lastPart Массив с основными данными об элементе
     * @param null|string $parentUrl
     * @return string Сгенерированный URL этого элемента
     * @noinspection MultipleReturnStatementsInspection
     */
    public static function getUrlWithPrefix(array $lastPart, ?string $parentUrl = null): string
    {
        $lastUrlPart = $lastPart['url'];

        $config = Config::getInstance();
        if ($parentUrl === null || $parentUrl === '' || $parentUrl === '/') {
            $parentUrl = $config->cms['startUrl'];
        }

        if ($parentUrl === '---') {
            // В случае, когда родительский url не определён
            return '---';
        }

        if ($lastUrlPart === '/' || $lastUrlPart === '') {
            $lastUrlPart = '/';
            // Ссылка на главную обрабатывается особым образом
            if ($config->cms['startUrl'] !== '') {
                $lastUrlPart = $config->cms['startUrl'] . '/';
            }
            return $lastUrlPart;
        }

        $pluginBroker = PluginBroker::getInstance();
        $arr = ['last' => $lastPart, 'parent' => $parentUrl];
        $arr = $pluginBroker->makeEvent('onGetUrl', $arr);
        $lastUrlPart = $arr['last']['url'];

        if (!empty($lastUrlPart) &&
            (strncmp($lastUrlPart, 'http:', 5) === 0
            || strncmp($lastUrlPart, 'https:', 6) === 0
            || strncmp($lastUrlPart, '/', 1) === 0)
        ) {
            // Если это уже сформированная или пустая ссылка, её и возвращаем
            return $lastUrlPart;
        }

        $url = $parentUrl . '/';

        // Добавляем дочерний url
        if ($url !== $lastUrlPart) {
            // Сработает для всех ссылок, кроме главной '/'
            $url .= $lastUrlPart . $config->urlSuffix;
        }

        return $url;
    }

    /**
     * Установка родительского URL ($this->parentUrl) на основании $path
     *
     * @param array $path Путь до элемента, для которого нужно определить URL
     * @return string Родительский URL, который можно использовать для построения URL
     * @noinspection MultipleReturnStatementsInspection
     */
    public function setParentUrl(array $path): string
    {
        // Обратиться к модели для получения своей части url, затем обратиться
        // к более старшим структурам пока не доберёмся до конца

        if (count($path) > 2 && $path[1]['url'] === '/') {
            // Мы находимся внутри главной - в ней url не работают
            return '---';
        }

        // TODO если первая структура не стартовая, то нужно определить путь от стартовой структуры

        // Если первая структура в пути — стартовая структура, то просто объединяем url

        if (!isset($path[0]['url'])) {
            // Путь может быть не задан в случае установки parentUrl для главной странице
            $this->parentUrl = '';
            return '';
        }

        $url = '';
        $prefix = '';

        // Объединяем все участки пути
        foreach ($path as $v) {
            if (isset($v['is_skip']) && $v['is_skip']) {
                continue;
            }
            if (strncmp($v['url'], 'http:', 5) === 0
                || strncmp($v['url'], 'https:', 6) === 0
                || strncmp($v['url'], '/', 1) === 0
            ) {
                // Если в одном из элементов пути есть ссылки на другие страницы, то путь построить нельзя
                return '---';
            }
            $url .= $prefix . $v['url'];
            $prefix = '/';
        }

        $this->parentUrl = $url;

        return $url;
    }

    /**
     * Транслитерация файлов без изменения букв в расширении
     *
     * @param string $name Исходное название файла
     * @return string Преобразованное название файла
     */
    public function translitFileName(string $name): string
    {
        $ext = '';
        $posDot = mb_strrpos($name, '.');
        if ($posDot !== 0) {
            $name = mb_substr($name, 0, $posDot);
            $ext = '.' . mb_substr($name, $posDot + 1);
        }
        $name = self::translit($name);
        $name = strtolower($name);
        $arr = [
            '@' => '',
            '$' => '',
            '^' => '',
            '+' => '',
            '|' => '',
            '{' => '',
            '}' => '',
            '>' => '',
            '<' => '',
            ':' => '',
            ';' => '',
            '[' => '',
            ']' => '',
            '\\' => '',
            '*' => '',
            '(' => '',
            ')' => '',
            '!' => '',
            '#' => 'N',
            '—' => '',
            '/' => '-',
            '«' => '',
            '»' => '',
            '.' => '',
            '№' => 'N',
            '"' => '',
            '\'' => '',
            '?' => '',
            ' ' => '-',
            '&' => '',
            ',' => '',
            '%' => ''
        ];
        $name = strtr($name, $arr);
        return $name . $ext;
    }

    /**
     * Построение url на основе cid элемента $part
     *
     * @param array $part Массив с данными элемента, для которого нужно построить url по cid
     * @param string $structureName Название структуры, в которой находится элемент
     *
     * @return string
     */
    public function getUrlByCid(array $part, string $structureName): string
    {
        $structureClass = explode('_', $structureName);
        $structureClass = '\\' . $structureClass[0] . '\\Structure\\' . $structureClass[1] . '\\Site\\Model';

        /** @var \Ideal\Structure\Part\Site\Model $structureModel */
        $structureModel = new $structureClass($part['prev_structure']);

        $structureModel->setPageData($part);
        $path = $structureModel->getLocalPath();
        array_pop($path);

        $url = [];
        foreach ($path as $item) {
            $url[] = $item['url'];
        }

        return self::getUrlWithPrefix($part, '/' . implode('/', $url));
    }

    /**
     * Установка пути для построения URL по prev_structure
     *
     * Используется для построения пути у повторяющихся элементов на одном уровне, если путь для них не построен
     * Например, список товаров в корзине и т.п.
     *
     * @param string $prevStructure prev_structure родительского элемента
     * @return array Путь, включая и родительский элемент
     * @throws Exception В случае, если родительский элемент не найден (неправильная prev_structure)
     */
    public function setPathByPrevStructure(string $prevStructure): array
    {
        $config = Config::getInstance();

        // Находим элемент по prevStructure
        [$structureId, $elementId] = explode('-', $prevStructure);
        $structure = $config->getStructureById($structureId);
        [$mod, $structure] = explode('_', $structure['structure']);
        $class = '\\' . $mod . '\\Structure\\' . $structure . '\\Site\\Model';

        /** @var \Ideal\Core\Site\Model $model */
        $model = new $class('');
        $model->initPageDataById($elementId);

        $path = $model->detectPath();
        $this->setParentUrl($path);

        return $path;
    }
}
