<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core;

use Ideal\Core\Admin;
use Ideal\Core\Site;
use Ideal\Structure\Error404\Site\Controller;
use Ideal\Structure\User\Admin\Plugin;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Front Controller объединяет всю обработку запросов, пропуская запросы через единственный объект-обработчик.
 *
 * После обработки запроса в роутере, фронт-контроллер запускает финальный контроллер, название которого,
 * вместе с моделью данных, определяется в роутере.
 */
class FrontController
{
    /**
     * Запуск FrontController'а
     *
     * Проводится роутинг, определяется контроллер страницы и отображаемый текст.
     * Выводятся HTTP-заголовки и отображается текст, сгенерированный с помощью view в controller
     *
     */
    public function run(): void
    {
        // Подключаем класс конфига
        $config = Config::getInstance();

        $rootDir = dirname(DOCUMENT_ROOT);

        // Загружаем список структур из конфигурационных файлов структур
        $config->loadSettings($rootDir);

        // Регистрируем плагин авторизации
        $pluginBroker = PluginBroker::getInstance();
        $pluginBroker->registerPlugin('onPostAdminDispatch', Plugin::class);

        $routes = new RouteCollection();

        // Добавляем маршрут админки
        $route = new Route('/' . $config->cmsFolder . '/', ['_controller' => Admin\Router::class]);
        $routes->add('admin', $route);

        /** @noinspection UsingInclusionReturnValueInspection */
        $callable = require $rootDir . '/config/routes.php';

        $routes = $callable($routes);

        $request = Request::createFromGlobals();
        $context = new RequestContext();
        $context->fromRequest($request);

        $referer = null;
        $matcher = new UrlMatcher($routes, $context);
        try {
            // todo сравнение по http-методам
            $parameters = $matcher->match($request->getPathInfo());
        } catch (ResourceNotFoundException $e) {
            // Стандартный роутинг не смог ничего найти, запускаем роутинг по БД
            $parameters = [
                '_controller' => Site\Router::class,
                'slug' => $request->getPathInfo(),
                '_route' => 'front',
            ];
            $referer = $this->getReferer($request);
        }

        $controllerName = $parameters['_controller'];
        $actionName = ($parameters['action'] ?? 'index') . 'Action';
        /** @var Response $response */
        $response = (new $controllerName())->$actionName($request);

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND && $response->getContent() === '') {
            $this->save404($request);
            $response = (new Controller())->errorAction($request);
        }

        if ($referer !== null) {
            $response->headers->setCookie(Cookie::create('referer', $referer, time() + 315360000));
        }

        $response->prepare($request);
        $response->send();
    }

    /**
     * Получение реферера пользователя и установка реферера в куки
     *
     * @param Request $request
     * @return string
     */
    protected function getReferer(Request $request): string
    {
        // Проверяем есть ли в куках информация о реферере
        $referer = $request->cookies->get('referer');
        if ($referer === null) {
            // Если информации о реферере нет в куках, то добавляем её туда
            if (!empty($request->server->get('HTTP_REFERER'))) {
                $referer = $request->server->get('HTTP_REFERER');
            } else {
                $referer = 'null';
            }
        }

        return $referer;
    }

    protected function save404(Request $request): void
    {
        $error404 = new \Ideal\Structure\Error404\Model();
        $error404->setUrl($request->getUri());
        $error404->save404();
    }

}
