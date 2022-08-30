<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\News\Site;

use Ideal\Core\Config;
use Ideal\Core\Request;

class ControllerAbstract extends \Ideal\Core\Site\Controller
{

    /** @var $model Model */
    protected $model;

    public function detailAction()
    {
        $this->templateInit('Structure/News/Site/detail.twig');

        $this->view->text = $this->model->getText();
        $this->view->header = $this->model->getHeader();

        $config = Config::getInstance();
        $parentUrl = $this->model->getParentUrl();
        $this->view->allNewsUrl = substr($parentUrl, 0, strrpos($parentUrl, '/')) . $config->urlSuffix;
    }

    public function indexAction()
    {
        parent::indexAction();

        $request = new Request();
        $page = (int)$request->{$this->pageName};

        $this->view->parts = $this->model->getList($page);
        $this->view->pager = $this->model->getPager($this->pageName);
    }
}
