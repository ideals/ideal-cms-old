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
use JsonException;

class ControllerAbstract extends \Ideal\Core\Site\Controller
{

    /** @var $model Model */
    protected $model;

    /**
     * @throws JsonException
     */
    public function detailAction(): void
    {
        $this->templateInit('Structure/News/Site/detail.twig');

        $this->view->set('text', $this->model->getText());
        $this->view->set('header', $this->model->getHeader());

        $config = Config::getInstance();
        $parentUrl = $this->model->getParentUrl();
        $this->view->set(
            'allNewsUrl',
            substr($parentUrl, 0, strrpos($parentUrl, '/')) . $config->urlSuffix
        );
    }

    public function indexAction(): void
    {
        parent::indexAction();

        $request = new Request();
        $page = (int)$request->{$this->pageName};

        $this->view->set('parts', $this->model->getList($page));
        $this->view->set('pager', $this->model->getPager($this->pageName));
    }
}
