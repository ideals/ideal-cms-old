<?php /** @noinspection ClassOverridesFieldOfSuperClassInspection */

/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Service\Admin;

use Exception;
use Ideal\Core\Request;

class ControllerAbstract extends \Ideal\Core\Admin\Controller
{

    /* @var $model Model */
    protected $model;

    /**
     * Магический метод, перехватывающий ajax-запросы и подключающий соответствующие файлы
     *
     * @param string $name      Название вызываемого метода
     * @param array $arguments Аргументы, передаваемые методу
     * @throws Exception Исключение, если для вызываемого метода нет соответствующего файла
     */
    public function __call(string $name, array $arguments)
    {
        $item = $this->model->getPageData();

        [$module, $structure] = explode('_', $item['ID']);
        $class = $module . '\\Structure\\Service\\' . $structure . '\\AjaxController';
        $object = new $class();

        $object->$name();
    }

    /**
     * @throws Exception
     */
    public function indexAction(): void
    {
        $this->templateInit('Structure/Service/Admin/index.twig');

        // Инициализируем объект запроса
        $request = new Request();
        $par = $request->get('par');
        $sepPar = strpos($par, '-');
        if ($sepPar !== false) {
            $this->view->set('par', substr($par, 0, $sepPar));
        } else {
            $this->view->set('par', $par);
        }

        $this->view->set('items', $this->model->getMenu()); // $structure['items']);

        $item = $this->model->getPageData();
        $this->view->set('ID', $item['ID']);

        [$module, $structure] = explode('_', $item['ID']);
        $className = $module . '\\Structure\\Service\\' . $structure . '\\Action';
        $action = new $className();

        $this->view->set('text', $action->render());
    }
}
