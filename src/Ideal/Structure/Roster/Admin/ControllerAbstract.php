<?php /** @noinspection ClassOverridesFieldOfSuperClassInspection */

/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Roster\Admin;

use Ideal\Core\Admin\Controller;
use Ideal\Core\Request;

class ControllerAbstract extends Controller
{

    /* @var $model ModelAbstract */
    protected $model;

    public function indexAction(): void
    {
        $this->templateInit();

        $request = new Request();

        // Считываем список элементов
        $page = (int)$request->page;
        $listing = $this->model->getListAcl($page);
        $headers = $this->model->getHeaderNames();

        $this->parseList($headers, $listing);

        $this->view->set('pager', $this->model->getPager('page'));
    }
}
