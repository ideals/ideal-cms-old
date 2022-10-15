<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Addon\YandexSearch;

use Exception;
use Ideal\Addon;
use Ideal\Core\Config;
use Ideal\Core\View;
use Ideal\Core\Request;
use AntonShevchuk\YandexXml\Client;
use AntonShevchuk\YandexXml\Exceptions\YandexXmlException;

/**
 * Класс аддона, обеспечивающий поиск по сайту
 *
 * Содержит в себе обращение к сервису Яндекс.XML либо по настройкам аддона, либо по глобальным настройкам
 * в CMS (файл config.php). Аддон содержит шаблон index.twig, подключаемый для генерации поля content аддона,
 * поэтому аддон можно подключать как обычный аддон Page, для которого никакой дополнительной кастомизации
 * в общем шаблоне не требуется.
 */
class SiteModel extends Addon\AbstractSiteModel
{

    /** @var int Общее количество результатов поиска */
    protected int $listCount = 0;

    /**
     * Получение данных аддона с выполнением всех действий (в данном случае — запроса к Яндексу)
     *
     * @return array Все данные аддона и сгенерированное поле content с отображаемым html-кодом
     *
     * @throws YandexXmlException
     */
    public function getPageData(): array
    {
        $this->setPageDataByPrevStructure($this->prevStructure);

        $mode = explode('\\', get_class($this->parentModel));

        if ($mode !== false && $mode[3] !== 'Site') {
            // Отображение поиска нужно только для фронтенда, в бэкенде просто возвращаем данные из БД
            return $this->pageData;
        }

        $config = Config::getInstance();

        // Подключаем шаблон аддона
        $tplRoot = dirname(stream_resolve_include_path('Addon/YandexSearch/index.twig'));
        $view = new View($tplRoot, $config->cache['templateSite']);
        $view->loadTemplate('index.twig');

        // Логин и ключ от сервиса Яндекс
        $yandexLogin = trim($this->pageData['yandexLogin']);
        $yandexKey = trim($this->pageData['yandexKey']);

        // Номер отображаемой страницы
        $request = new Request();
        $page = (int)$request->get('num', 0);
        $page = ($page === 0) ? 1 : $page;
        $page--;

        // Поисковый запрос
        $request = new Request();
        $query = trim($request->get('query'));
        $view->set('query', $query);

        if (!empty($query)) {
            if (empty($yandexLogin) || empty($yandexKey)) {
                $yandexLogin = trim($config->yandex['yandexLogin']);
                $yandexKey = trim($config->yandex['yandexKey']);
            }

            // Для фронтенда рендерим результат поиска
            if (!empty($yandexLogin) && !empty($yandexKey)) {
                $yandexRequest = Client::request($yandexLogin, $yandexKey);

                // Параметр необходимый для получения листалки
                $elementsSite = $this->pageData['elements_site'];
                $this->params['elements_site'] = !empty($elementsSite) ? $elementsSite : 15;

                try {
                    $yandexResponse = $yandexRequest
                        ->query($query) // запрос к поисковику
                        ->site($config->domain)// ограничиваемся поиском по сайту
                        ->page($page) // начать со страницы, по умолчанию 0 (первая страница)
                        ->limit($this->params['elements_site']) // Количество результатов на странице (макс 100)
                        ->send() // Возвращает объект Response
                    ;
                } catch (Exception $e) {
                    $view->set('message', $e->getMessage());
                }
                if (isset($yandexResponse)) {
                    $list = $yandexResponse->results();

                    // Передаём данные в шаблон для рендера поиска
                    $this->listCount = $yandexResponse->total();
                    $view->set('total', $this->listCount);
                    $view->set('parts', $list);
                    $view->set('pager', $this->getPager('num'));
                    $page++;
                    $view->set('startList', $page * $this->pageData['elements_site'] - $this->pageData['elements_site'] + 1);
                }
            } else {
                $view->set('message', 'Поле логин или ключ от яндекса имеет пустое значение');
            }
        } else {
            $view->set('message', 'Пустой поисковый запрос');
        }
        $this->pageData['content'] .= $view->render();

        return $this->pageData;
    }

    /**
     * Используется в методе "getPager"
     *
     * @return int Общее количество результатов поиска
     */
    public function getListCount(): int
    {
        return $this->listCount;
    }
}
