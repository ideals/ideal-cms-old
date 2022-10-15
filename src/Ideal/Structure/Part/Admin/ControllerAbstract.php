<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Part\Admin;

use Exception;
use Ideal\Core\Request;
use Ideal\Core\Util;

class ControllerAbstract extends \Ideal\Core\Admin\Controller
{

    /* @var $model Model */
    protected $model;

    /**
     * @return void
     */
    public function indexAction(): void
    {
        $this->templateInit();

        // Считываем список элементов
        $request = new Request();
        $page = (int)$request->page;
        $listing = $this->model->getListAcl($page);
        $headers = $this->model->getHeaderNames();

        $this->parseList($headers, $listing);

        $this->view->set('pager', $this->model->getPager('page'));
    }

    public function showCreateTemplateAction(): void
    {
        $request = new Request();
        $template = $request->get('template');
        $templateModelName = Util::getClassName($template, 'Template') . '\\Model';
        /* @var $model \Ideal\Core\Admin\Model */
        $model = new $templateModelName('не имеет значения');
        $model->setFieldsGroup($request->get('name'));
        $model->setPageDataNew();
        echo $model->getFieldsList($model->fields);
        exit;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function showEditTemplateAction(): void
    {
        $request = new Request();

        $this->model->setPageDataById($request->get('id'));
        $pageData = $this->model->getPageData();

        $template = $request->get('template');
        $templateModelName = Util::getClassName($template, 'Template') . '\\Model';
        $model = new $templateModelName($template, $pageData['prev_structure']);
        $model->setFieldsGroup($request->get('name'));
        // Загрузка данных связанного объекта
        if (isset($pageData['ID'])) {
            $prevStructure = $pageData['prev_structure'] . '-' . $pageData['ID'];
            $model->setPageDataByPrevStructure($prevStructure);
        }

        echo $model->getFieldsList($model->fields);
        exit;
    }
}
