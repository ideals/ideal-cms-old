<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core\Admin;

use Ideal\Core\Config;
use Ideal\Core\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Контроллер, вызываемый при работе с ajax-вызовами
 */
class AjaxController
{
    /** @var array Дополнительные HTTP-заголовки ответа  */
    protected array $httpHeaders = [
        'X-Robots-Tag' => 'noindex, nofollow',
        'Content-Type' => 'application/json',
    ];

    /* @var View Объект вида — twig-шаблонизатор */
    protected View $view;

    /**
     * Генерация контента страницы для отображения в браузере
     *
     * @param Router $router
     *
     * @return Response
     */
    public function run(Router $router): Response
    {
        $this->model = $router->getModel();

        // Определяем и вызываем требуемый action у контроллера
        $request = $router->getRequest();
        $actionName = $request->get('action', 'index') . 'Action';
        $this->$actionName();

        if (!method_exists($this, $actionName)) {
            throw new ResourceNotFoundException(sprintf(
                'Не найден экшен %s в классе %s',
                $actionName,
                get_class($this)
            ));
        }

        $response = (new Response())->setContent($this->$actionName());

        $response->headers->add($this->getHttpHeaders());

        // Вызываемый action существует, запускаем его
        return $response;
    }

    /**
     * Получение дополнительных HTTP-заголовков
     * По умолчанию система ставит только заголовок Content-Type, но и его можно
     * переопределить в этом методе.
     *
     * @return array Массив, где ключи - названия заголовков, а значения - содержание заголовков
     */
    public function getHttpHeaders(): array
    {
        return $this->httpHeaders;
    }


    public function setContentType(string $type): self
    {
        $this->httpHeaders['Content-Type'] = $type;

        return $this;
    }

    /**
     * Генерация шаблона отображения
     *
     * @param string $tplName
     */
    public function templateInit(string $tplName = ''): void
    {
        if (!stream_resolve_include_path($tplName)) {
            echo 'Нет файла шаблона ' . $tplName;
            exit;
        }
        $tplRoot = dirname(stream_resolve_include_path($tplName));
        $tplName = basename($tplName);

        // Определяем корневую папку системы для подключения шаблонов из любой вложенной папки через их путь
        $config = Config::getInstance();
        $cmsFolder = DOCUMENT_ROOT . '/' . $config->cmsFolder;

        $folders = [$tplRoot, $cmsFolder];
        $this->view = new View($folders, $config->cache['templateSite']);
        $this->view->loadTemplate($tplName);
    }
}
