<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core\Admin;

use Ideal\Core;
use Ideal\Core\Config;
use Ideal\Core\Request;
use Ideal\Core\Util;
use Ideal\Core\View;
use Ideal\Setup\ModuleConfig;
use Ideal\Structure;
use Ideal\Core\FileCache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Controller
{
    /** @var Model Модель соответствующая этому контроллеру */
    protected $model;

    /** @var array Путь к этой странице, включая и её саму */
    protected array $path;

    /** @var View Объект вида — twig-шаблонизатор */
    protected View $view;

    public function createAction()
    {
        $this->model->setPageDataNew();

        // Проверка ввода - если ок - сохраняем, если нет - сообщаем об ошибках
        $result = $this->model->parseInputParams(true);

        if ($result['isCorrect']) {
            $result = $this->model->createElement($result);
            $this->runClearFileCache();
            if ($result['isCorrect']) {
                $this->model->saveToLog('Создан');
            }
        }

        echo json_encode($result);
        exit;
    }

    public function deleteAction()
    {
        $request = new Request();

        $result = array();
        $result['ID'] = intval($request->id);
        $result['isCorrect'] = false;

        $this->model->setPageDataById($result['ID']);

        $aclModel = new \Ideal\Structure\Acl\Admin\Model();
        // Проверяем, есть ли право удаления элемента
        if ($aclModel->checkAccess($this->model, 'delete')) {
            $result['isCorrect'] = $this->model->delete();
        }

        if ($result['isCorrect'] == 1) {
            $this->runClearFileCache();
            $this->model->saveToLog('Удалён');
        }

        echo json_encode($result);
        exit;
    }

    public function editAction()
    {
        $request = new Request();
        $this->model->setPageDataById($request->id);

        // Проверка ввода - если ок - сохраняем, если нет - сообщаем об ошибках
        $result = $this->model->parseInputParams();

        $aclModel = new \Ideal\Structure\Acl\Admin\Model();
        // Проверяем, есть ли право редактирования элемента
        if ($result['isCorrect'] == 1) {
            $result['isCorrect'] = $aclModel->checkAccess($this->model, 'edit');
        }

        if ($result['isCorrect'] == 1) {
            $result = $this->model->saveElement($result);
            $this->runClearFileCache();
            $this->model->saveToLog('Изменён');
        }

        echo json_encode($result);
        exit;
    }

    /**
     * Инициализация админского twig-шаблона
     *
     * @param string $tplName Название файла шаблона (с путём к нему), если не задан - будет index.twig
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function templateInit(string $tplName = 'index.twig'): void
    {
        $config = Config::getInstance();

        // Инициализация общего шаблона страницы
        $roots = [];
        $gblRoot = $config->rootDir . '/src/Ideal';
        if (file_exists($gblRoot)) {
            $roots[] = $gblRoot;
        }

        $idealModuleConfig = new ModuleConfig();
        $roots[] = $config->getModulePath($idealModuleConfig) . '/Ideal';

        $classPath = dirname(str_replace('\\', '/', get_class($this)));
        $tplRoot = $config->rootDir . '/src/' . $classPath;
        if (file_exists($tplRoot)) {
            $roots[] = $tplRoot;
        }

        $roots[] = $config->getModulePath($this) . '/' . $classPath;

        // Инициализируем Twig-шаблонизатор
        $config = Config::getInstance();
        $this->view = new View(
            array_unique($roots),
            $config->cache['templateAdmin']
        );
        $this->view->loadTemplate($tplName);
    }

    /**
     * Получение дополнительных HTTP-заголовков
     * По умолчанию система ставит только заголовок Content-Type, но и его можно
     * переопределить в этом методе.
     *
     * @return array Массив где ключи - названия заголовков, а значения - содержание заголовков
     */
    public function getHttpHeaders()
    {
        return array(
            'X-Robots-Tag' => 'noindex, nofollow'
        );
    }

    // TODO перенести в контроллер юзера

    public function logoutAction()
    {
        $user = Structure\User\Model::getInstance();
        $user->logout();
        header('Location: .');
        exit;
    }

    public function parseList($headers, $list)
    {
        $config = Config::getInstance();

        // Инициализируем объект запроса
        $request = new Request();

        // Отображение списка заголовков
        $this->view->headers = $headers;

        if ($request->par == '') {
            $request->par = 1;
        }
        $this->view->par = $request->par;

        // Отображение списка элементов
        $rows = [];
        foreach ($list as $k => $v) {
            $fields = '';
            foreach ($headers as $key => $v2) {
                $type = $this->model->fields[$key]['type'];
                $fieldClassName = Util::getClassName($type, 'Field') . '\\Controller';
                $fieldModel = $fieldClassName::getInstance();
                $fieldModel->setModel($this->model, $key);
                $value = $fieldModel->getValueForList($v, $key);
                if (isset($this->model->params['field_name']) && $key == $this->model->params['field_name']
                    && (!isset($v['acl']) || $v['acl']['enter']) ) {
                    // На активный элемент ставим ссылку
                    $par = $request->par . '-' . $v['ID'];
                    $value = '<a href="?par=' . $par . '">' . $value . '</a>';
                }
                $fields .= '<td>' . $value . '</td>';
            }
            $rows[] = [
                'ID' => $v['ID'],
                'row' => $fields,
                'is_active' => $v['is_active'] ?? 1,
                'is_not_menu' => $v['is_not_menu'] ?? 0,
                'acl_edit' => $v['acl']['edit'] ?? 1,
                'acl_delete' => $v['acl']['delete'] ?? 1,
                'acl_enter' => $v['acl']['enter'] ?? 1,
            ];
        }
        $this->view->rows = $rows;
    }

    /**
     * Генерация контента страницы для отображения в браузере
     *
     * @param Router $router
     * @return string Содержимое отображаемой страницы
     */
    public function run(Router $router)
    {
        $this->model = $router->getModel();

        // Определяем и вызываем требуемый action у контроллера
        $request = $router->getRequest();
        $actionName = $request->get('action', 'index') . 'Action';
        if (!method_exists($this, $actionName)) {
            throw new ResourceNotFoundException(sprintf(
                'Не найден экшен %s в классе %s',
                $actionName,
                get_class($this)
            ));
        }

        $response = $this->$actionName() ?? new Response();

        $config = Config::getInstance();

        $vars = [
            'domain' => strtoupper($config->domain),
            'cmsFolder' => $config->cmsFolder,
            'subFolder' => $config->cms['startUrl'],
            'title' => $this->model->getTitle(),
            'header' => $this->model->getHeader(),
        ];

        // Регистрируем объект пользователя
        /* @var $user Structure\User\Model */
        $user = Structure\User\Model::getInstance();
        if (isset($user->data['ID'])) {
            $prev = $user->data['prev_structure'];
            // todo обычно юзеры всегда на первом уровне, но нужно доделать чтобы работало не только для первого уровня
            $user->data['par'] = substr($prev, strrpos($prev, '-') + 1);
        }
        $vars['user'] = $user->data;

        // Отображение верхнего меню структур
        $aclModel = new \Ideal\Structure\Acl\Admin\Model();
        $vars['structures'] = $aclModel->filterShow(0, $config->structures);
        $path = $this->model->getPath();
        $vars['activeStructureId'] = $path[0]['ID'];

        // Отображение хлебных крошек
        $breadCrumbs = [];
        $pars = $breadCrumbs;
        foreach ($path as $v) {
            $pars[] = $v['ID'];
            $breadCrumbs[] = [
                'link' => implode('-', $pars),
                'name' => $v['name']
            ];
        }
        $vars['breadCrumbs'] = $breadCrumbs;

        $vars['toolbar'] = $this->model->getToolbar();

        $vars['hideToolbarForm'] = !is_array($request->get('toolbar')) || (count($request->get('toolbar')) === 0);

        // Определение места выполнения скрипта (на сайте в production, или локально в development)
        $vars['isProduction'] = $config->domain === str_replace('www.', '', $_SERVER['HTTP_HOST']);

        $this->view->mergeVars($vars);

        $this->finishMod($actionName);

        return $response->setContent($this->view->render());
    }

    /**
     * Внесение финальных изменений в шаблон, после всех-всех-всех
     *
     * @param string $actionName
     */
    public function finishMod($actionName)
    {
    }

    public function showCreateAction()
    {
        $this->model->setPageDataNew();
        // Отображаем список полей структуры part
        $this->showEditTabs();
        exit;
    }

    protected function showEditTabs($values = '')
    {
        $model = $this->model;
        // Выстраиваем список табов
        $defaultName = 'Основное';
        $tabs = array($defaultName => array());
        foreach ($model->fields as $fieldName => $field) {
            if (isset($field['tab'])) {
                $tabs[$field['tab']][$fieldName] = $field;
            } else {
                $tabs[$defaultName][$fieldName] = $field;
            }
        }
        $tabLine = '<ul class="nav nav-tabs" id="tabs">';
        $tabsContent = '<div class="tab-content" id="tabs-content">';
        $isActive = ' active';
        $num = 0;
        foreach ($tabs as $tabName => $tab) {
            $num++;
            $tabLine .= '<li class="' . $isActive . '"><a href="#tab' . $num . '" data-toggle="tab">' . $tabName
                . '</a></li>';
            $tabsContent .= '<div class="tab-pane' . $isActive . '" id="tab' . $num . '">';
            $tabsContent .= $model->getFieldsList($tab);
            $tabsContent .= '</div>';
            $isActive = '';
        }
        $tabLine .= '</ul>';
        $tabsContent .= '</div>';
        echo json_encode([
            'tabs' => $tabLine,
            'content' => $tabsContent
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function showEditAction()
    {
        $request = new Request();
        $this->model->setPageDataById($request->id);
        // TODO доработать $this->model->getPath() так, чтобы в пути присутствовала и главная
        $this->showEditTabs();
        exit;
    }

    /**
     * Запуск очищения файлового кэша.
     */
    public function runClearFileCache()
    {
        $config = Config::getInstance();
        $configCache = $config->cache;

        // Очищаем файловый кэш  при условии что кэширование включено.
        // Если кэширование выключено кэш должен быть пуст
        if (isset($configCache['fileCache']) && $configCache['fileCache']) {
            FileCache::clearFileCache();
        }
    }
}
