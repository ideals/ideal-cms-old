<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core\Site;

use Ideal\Core;
use Ideal\Core\Config;
use Ideal\Core\View;
use Ideal\Setup\ModuleConfig;
use Ideal\Structure\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class Controller
{

    /** @var bool Включение листалки (пагинации) */
    protected $isPager = true;

    /* @var $model Model Модель соответствующая этому контроллеру */
    protected $model;

    /** @var string Название параметра листалки */
    protected $pageName = 'page';

    /* @var $path array Путь к этой странице, включая и её саму */
    protected $path;

    /** @var string Имя файла с нестандартным шаблоном view */
    protected $tplName = '';

    /* @var $view View Объект вида — twig-шаблонизатор */
    protected $view;

    /**
     * Инициализация twig-шаблона сайта
     *
     * @param string $tplName Название файла шаблона (с путём к нему), если не задан - будет index.twig
     */
    public function templateInit(string $tplName = 'index.twig')
    {
        // Если вьюха уже установлена, то ничего делать не надо
        // для переустановки вьюхи надо придумать отдельный метод, когда это потребуется
        if (isset($this->view)) {
            return;
        }

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
            // Дата последней модификации страницы
            // 'Last-Modified' => gmdate('D, d M Y H:i:s', $lastMod ) . ' GMT',
            // Затирание информации о языке, на котором написан сайт
            // 'X-Powered-By' => 'Hello, man!',
            // Дата завершения срока годности странички :)
            // 'Expires' => gmdate('D, d M Y H:i:s')+900 . ' GMT\r\n',
            // Варианты управления кэшем. Можно выбрать только один из вариантов.
            // 'Cache-Control' => 'no-store, no-cache, must-revalidate',
            // 'Cache-Control' => 'post-check=0, pre-check=0',
            // 'Cache-Control' => 'Pragma: no-cache',
        );
    }

    /**
     * Действие по умолчанию для большинства контроллеров внешней части сайта.
     * Выдёргивает контент из связанного шаблона и по этому контенту определяет заголовок (H1)
     *
     */
    public function indexAction()
    {

        // Выдёргиваем заголовок из addonName[key]['content']
        $header = $this->model->getHeader();

        $tplName = '';
        $pageData = $this->model->getPageData();

        //Определяем шаблон для отображения
        if (!empty($pageData['template'])) {
            $tplName = $this->getPathToTwigTemplate($pageData['template']);
        }

        $this->templateInit($tplName);

        $this->view->header = $header;

        // Перенос данных страницы в шаблон
        if (is_array($pageData)) {
            foreach ($pageData as $k => $v) {
                $this->view->$k = $v;
            }
        }

        $request = new Request();
        $page = (int)$request->{$this->pageName};

        if ($page > 1) {
            // На страницах листалки описание категории отображать не надо
            if (isset($pageData['addons'])) {
                for ($i = 0; $i < count($pageData['addons']); $i++) {
                    $this->view->addons[$i]['content'] = '';
                }
            }
        } elseif ($page === 1) {
            // Устраняем из адреса параметр с номером страницы
            $url = $this->model->getCanonical();
            $url = $request->getQueryWithout($this->pageName, $url);
            $this->redirect($url);
        }
    }

    /**
     * Отображение структуры в браузере
     *
     * @param Router $router
     *
     * @return Response
     */
    public function run(Router $router): Response
    {
        $this->model = $router->getModel();
        $request = $router->getRequest();

        // Определяем и вызываем требуемый action у контроллера
        $actionName = $request->get('action', 'index') . 'Action';

        if (!method_exists($this, $actionName)) {
            // Вызываемый action отсутствует, запускаем 404 ошибку
            throw new ResourceNotFoundException(sprintf(
                'Не найден экшен %s в классе %s',
                $actionName,
                get_class($this)
            ));
        }

        // Вызываемый action существует, запускаем его
        $this->$actionName();

        // Заполняем шаблон общими данными сайта
        $this->fillView();

        // Проводим финальные модификации контента в контроллере отображаемой структуры
        $this->finishMod($actionName);

        // Twig рендерит текст странички из шаблона
        $text = $this->view->render();

        // Проводим финальные модификации страницы, общие для всех страниц
        $helper = new Helper();
        if (method_exists($helper, 'finishMod')) {
            $text = $helper->finishMod($text);
        }

        return new Response($text, $this->model->is404 ? Response::HTTP_NOT_FOUND : Response::HTTP_OK);
    }

    protected function fillView()
    {
        $config = Config::getInstance();

        $this->view->domain = strtoupper($config->domain);
        $this->view->startUrl = $config->cms['startUrl'];
        $this->view->minifier = $config->cache['jsAndCss']; // флаг включения минификации js и css

        $this->view->breadCrumbs = $this->model->getBreadCrumbs();

        $this->view->year = date('Y');

        // Определение места выполнения скрипта (на сайте в production, или локально в development)
        $this->view->isProduction = $config->domain == str_replace('www.', '', $_SERVER['HTTP_HOST']);

        // Определение залогинен пользователь в админку или нет
        $user = new User\Model();
        $this->view->isAdmin = $user->checkLogin();

        $helper = new Helper();
        $helpers = $helper->getVariables($this->model);
        foreach ($helpers as $k => $v) {
            $this->view->$k = $v;
        }

        $this->view->title = $this->model->getTitle();
        $this->view->metaTags = $this->model->getMetaTags($helper->xhtml);
        $this->view->canonical = $this->model->getCanonical();
    }

    /**
     * Сеттер, необходимый для вызова экшенов контроллера из других контроллеров
     *
     * @param $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    public function getView()
    {
        return $this->view;
    }

    /**
     * Внесение финальных изменений в шаблон, после всех-всех-всех
     *
     * @param string $actionName
     */
    public function finishMod($actionName)
    {
    }

    /**
     * Установка нестандартного шаблона View
     *
     * @param string $tplName Путь к файлу шаблона от Ideal или от Mods (не включая эти папки)
     */
    public function setTemplate($tplName)
    {
        $this->tplName = $tplName;
    }

    /**
     * Получение пути до twig шаблона структуры
     *
     * @param string $tplName Тип класса (например, Structure или Field)
     * @return string
     */
    protected function getPathToTwigTemplate($tplName)
    {
        // Если был введён полный путь, то он используется напрямую, иначе только имя
        // Считаем, что был введён полный путь если присутствует хотя бы один слэш
        if (strpos($tplName, '/') !== false) {
            return $tplName;
        }
        $parts = explode('\\', get_class($this));
        $moduleName = ($parts[0] == 'Ideal') ? '' : $parts[0] . '/';
        return $moduleName . $parts[1] . '/' . $parts[2] . '/Site/' . $tplName;
    }

    /**
     * Редирект по указанному адресу
     * @param string $url Адрес для редиректа
     */
    protected function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }
}
