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
use Ideal\Core\Config;
use Ideal\Core\PluginBroker;
use Ideal\Core\Util;
use Symfony\Component\HttpFoundation\Request;
use Ideal\Structure\User\Admin\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class Router
{

    /** @var string Название контроллера активной страницы */
    protected string $controllerName = '';

    /** @var Model|null Модель активной страницы */
    protected ?Model $model = null;

    protected Request $request;

    /**
     * Производит роутинг исходя из запрошенного URL-адреса
     *
     * Генерирует событие onPreAdminDispatch, затем определяет модель активной страницы
     * и генерирует событие onPostAdminDispatch.
     * В результате работы конструктора инициализируются переменные $this->model и $this->ControllerName
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function indexAction(Request $request): Response
    {
        $this->request = $request;

        $pluginBroker = PluginBroker::getInstance();
        $pluginBroker->makeEvent('onPreAdminDispatch', $this);

        if ($this->model === null) {
            $this->model = $this->routeByPar($request->get('par', ''));
        }

        $pluginBroker->makeEvent('onPostAdminDispatch', $this);

        if ($request->get('mode') !== 'ajax') {
            // Инициализируем данные модели
            $this->model->initPageData();

            // Проверка прав доступа
            $aclModel = new \Ideal\Structure\Acl\Admin\Model();
            if (!$aclModel->checkAccess($this->model)) {
                // Если доступ запрещён, перебрасываем на соответствующий контроллер
                $this->controllerName = Controller::class;
                $request->query->set('action', 'accessDenied');
            }

            // Определяем корректную модель на основании поля structure
            $this->model = $this->model->detectActualModel();
        }

        if ($this->model !== null && $this->model->is404) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $controllerName = $this->getControllerName($request);

        // Запускаем нужный контроллер и передаём ему навигационную цепочку
        /* @var Controller $controller */
        $controller = new $controllerName();

        // Запускаем в работу контроллер структуры
        try {
            $response = $controller->run($this);
        } /** @noinspection BadExceptionsProcessingInspection */ catch (ResourceNotFoundException $exception) {
            // todo логирование $exception при включённом флаге отладки
            $response = new Response('', Response::HTTP_NOT_FOUND);
        }

        return $response;
    }

    /**
     * Определение модели активной страницы и пути к ней на основе переменной $_GET['par']
     *
     * @param $par
     * @return Model Модель активной страницы
     * @throws Exception
     * @noinspection OffsetOperationsInspection
     */
    protected function routeByPar($par): Model
    {
        $config = Config::getInstance();

        // Инициализируем $par — массив ID к активному объекту

        if ($par === '') {
            // par не задан, берём стартовую структуру из списка структур
            $path = [$config->getStartStructure()];
            $prevStructureId = $path[0]['ID'];
            $par = [];
        } else {
            // par задан, нужно его разложить в массив
            $par = explode('-', $par);
            // Определяем первую структуру
            $prevStructureId = $par[0];
            $path = [$config->getStructureById($prevStructureId)];
            unset($par[0]); // убираем первый элемент - ID начальной структуры
        }

        $is404 = false;
        if (!isset($path[0]['structure'])) {
            // По par ничего не нашлось, берём стартовую структуру из списка структуру
            $path = [$config->getStartStructure()];
            $prevStructureId = $path[0]['ID'];
            $par = [];
            $is404 = true;
        }

        $modelClassName = Util::getClassName($path[0]['structure'], 'Structure') . '\\Admin\\Model';
        /* @var $structure Model */
        $structure = new $modelClassName('0-' . $prevStructureId);
        $structure->is404 = $is404;

        // Запускаем определение пути и активной модели по $par
        return $structure->detectPageByIds($path, $par);
    }

    /**
     * Возвращает название контроллера для активной страницы
     *
     * @param Request $request
     * @return string  Название контроллера
     * @noinspection MultipleReturnStatementsInspection
     */
    public function getControllerName(Request $request): string
    {
        if ($this->controllerName !== '') {
            return $this->controllerName;
        }

        if (method_exists ($this->model, 'getControllerName')) {
            return $this->model->getControllerName();
        }

        if ($request->get('mode') === 'ajax' && $request->get('controller') !== '') {
            // Если это ajax-вызов с явно указанным namespace класса ajax-контроллера
            return $request->get('controller') . '\\AjaxController';
        }

        $path = $this->model->getPath();
        $end = end($path);

        if ($request->get('mode') === 'ajax' && $request->get('controller') === '') {
            // Если это ajax-вызов без указанного namespace класса ajax-контроллера,
            // то используем namespace модели
            return Util::getClassName($end['structure'], 'Structure') . '\\Admin\\AjaxController';
        }

        return Util::getClassName($end['structure'], 'Structure') . '\\Admin\\Controller';
    }

    /**
     * Устанавливает название контроллера для активной страницы
     *
     * Обычно используется в обработчиках событий onPreDispatch, onPostDispatch
     *
     * @param $name string Название контроллера
     */
    public function setControllerName(string $name): void
    {
        $this->controllerName = $name;
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
     * Возвращает статус 404-ошибки, есть он или нет
     */
    public function is404(): bool
    {
        return $this->model->is404;
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
     * @return Router
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }
}
