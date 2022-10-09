<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core\Site;

use Exception;
use Ideal\Core\Config;
use Ideal\Core\PluginBroker;
use Ideal\Core\Util;
use Ideal\Structure\Home;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class Router
{

    /** @var string Название контроллера активной страницы */
    protected string $controllerName = '';

    /** @var null|Model Модель активной страницы */
    protected ?Model $model = null;

    protected Request $request;

    /**
     * Производит роутинг исходя из запрошенного URL-адреса
     *
     * Конструктор генерирует событие onPreDispatch, затем определяет модель активной страницы
     * и генерирует событие onPostDispatch.
     * В результате работы конструктора инициализируются переменные $this->model и $this->ControllerName
     *
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     * @noinspection BadExceptionsProcessingInspection
     */
    public function indexAction(Request $request): Response
    {
        $this->request = $request;

        $pluginBroker = PluginBroker::getInstance();
        $pluginBroker->makeEvent('onPreDispatch', $this);

        if ($this->model === null && $request->get('mode') !== 'ajax') {
            $this->model = $this->routeByUrl($request);
        }

        $pluginBroker->makeEvent('onPostDispatch', $this);

        if ($this->model->is404) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        // Инициализируем данные модели
        $this->model->initPageData();

        // Определяем корректную модель на основании поля structure
        $this->model = $this->model->detectActualModel();

        try {
            $controllerName = $this->getControllerName($request);
        } catch (RuntimeException $e) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        // Запускаем нужный контроллер и передаём ему навигационную цепочку
        /* @var Controller $controller */
        $controller = new $controllerName();

        // Запускаем в работу контроллер структуры
        try {
            $response = $controller->run($this);
        } catch (ResourceNotFoundException $exception) {
            // todo логирование $exception при включённом флаге отладки
            $response = new Response('', Response::HTTP_NOT_FOUND);
        }

        return $response;
    }

    /**
     * Определение модели активной страницы и пути к ней на основе запрошенного URL
     *
     * @return Model Модель активной страницы
     * @throws Exception
     */
    protected function routeByUrl(Request $request): Model
    {
        $config = Config::getInstance();

        // Находим начальную структуру
        $path = [$config->getStartStructure()];
        $prevStructureId = $path[0]['ID'];

        $url = $this->prepareUrl($request->getRequestUri());

        // Если запрошена главная страница
        if ($url === '') {
            $model = new Home\Site\Model('0-' . $prevStructureId);
            return $model->detectPageByUrl($path, '/');
        }

        // Определяем оставшиеся элементы пути
        $modelClassName = Util::getClassName($path[0]['structure'], 'Structure') . '\\Site\\Model';
        /** @var Model $model */
        $model = new $modelClassName('0-' . $prevStructureId);

        // Проверяем наличие адреса среди уже известных 404-ых
        $error404 = new \Ideal\Structure\Error404\Model();
        if ($error404->isKnown404($url)) {
            return $model->set404(true);
        }

        // Определяем, заканчивается ли URL на правильный суффикс, если нет — 404
        $is404 = false;
        $lengthSuffix = strlen($config->urlSuffix);
        if ($lengthSuffix > 0) {
            $suffix = substr($url, -$lengthSuffix);
            if ($suffix !== $config->urlSuffix) {
                $is404 = true;
            }
            $url = substr($url, 0, -$lengthSuffix); // убираем суффикс из url
        }

        // Проверка, не остался ли в конце URL слэш
        if (substr($url, -1) === '/') {
            // Убираем завершающие слэши, если они есть
            $url = rtrim($url, '/');
            // Т.к. слэшей быть не должно (если они — суффикс, то они убираются выше)
            // то ставим 404-ошибку
            $is404 = true;
        }

        // Разрезаем URL на части
        $url = explode('/', $url);

        // Запускаем определение пути и активной модели по $par
        $model = $model->detectPageByUrl($path, $url);
        if (!$model->is404 && $is404) {
            // Если роутинг нашёл нужную страницу, но суффикс неправильный
            $model->is404 = true;
        }

        return $model;
    }

    /**
     * Возвращает название контроллера для активной страницы
     *
     * @return string Название контроллера
     */
    public function getControllerName(Request $request): string
    {
        if ($this->controllerName !== '') {
            return $this->controllerName;
        }

        $path = $this->model->getPath();

        if ($this->model !== null && count($path) === 0) {
            $this->model->is404 = true;
            // Эта проблема может возникнуть, только если что-то неправильно запрограммировано
            throw new RuntimeException('Не удалось построить путь. Модель: ' . get_class($this->model));
        }
        $end = array_pop($path);
        $prev = array_pop($path);

        if ($end['url'] === '/') {
            // Если запрошена главная страница, принудительно устанавливаем структуру Ideal_Home
            $structure = 'Ideal_Home';
        } elseif (isset($end['structure'])) {
            // В обычном случае название отображаемой структуры определяется по соответствующему
            // полю последнего элемента пути
            $structure = $end['structure'];
        } else {
            // Если в последнем элементе нет поля structure (например в новостях), то берём название
            // структуры из предыдущего элемента пути
            $structure = $prev['structure'];
        }

        if ($request->get('mode') === 'ajax') {
            if ($request->get('controller') === '') {
                // Если это ajax-вызов без указанного namespace класса ajax-контроллера,
                // то используем namespace модели
                $controllerName = Util::getClassName($end['structure'], 'Structure') . '\\Site\\AjaxController';
            } else {
                // Проверка на простой AJAX-запрос с указанием контроллера
                $controllerName = $request->get('controller') . '\\AjaxController';
            }
        } else {
            $controllerName = Util::getClassName($structure, 'Structure') . '\\Site\\Controller';
        }

        if (!class_exists($controllerName)) {
            throw new RuntimeException('Нет такого контроллера: ' . $controllerName);
        }

        return $controllerName;
    }

    /**
     * Устанавливает название контроллера для активной страницы
     *
     * Обычно используется в обработчиках событий onPreDispatch, onPostDispatch
     *
     * @param $name string Название контроллера
     *
     * @return $this
     */
    public function setControllerName(string $name): self
    {
        $this->controllerName = $name;

        return $this;
    }

    /**
     * Возвращает объект модели активной страницы
     *
     * @return Model Инициализированный объект модели активной страницы
     */
    public function getModel(): ?Model
    {
        return $this->model;
    }

    /**
     * @param $model Model Устанавливает модель, найденную роутером (обычно используется в плагинах)
     *
     * @return $this
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Возвращает статус 404-ошибки, есть он или нет
     */
    public function is404(): bool
    {
        return $this->model->is404;
    }

    /**
     * Зачистка url перед роутингом по нему
     *
     * @param string $url
     * @param bool $stripQuery Нужно ли удалять символы после ?
     * @return string
     */
    protected function prepareUrl(string $url, bool $stripQuery = true): string
    {
        $config = Config::getInstance();

        // Вырезаем стартовый URL
        $url = ltrim($url, '/');

        // Удаляем параметры из URL (текст после символа "#")
        $url = preg_replace('/\#.*/', '', $url);

        if ($stripQuery) {
            // Удаляем параметры из URL (текст после символа "?")
            $url = preg_replace('/[\?\#].*/', '', $url);
        }

        // Убираем начальные слэши и начальный сегмент, если cms не в корне сайта
        return ltrim(substr($url, strlen($config->cms['startUrl'])), '/');
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @param Request $request
     *
     * @return $this
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }
}
