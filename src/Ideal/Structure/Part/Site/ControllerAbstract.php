<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Part\Site;

use Ideal\Core;
use Ideal\Core\Request;

class ControllerAbstract extends Core\Site\Controller
{
    public function indexAction(): void
    {
        parent::indexAction();

        $request = new Request();
        $page = (int)$request->page;
        $this->view->set('parts', $this->model->getList($page));

        $this->view->set('pager', $this->model->getPager('page'));
    }
}
