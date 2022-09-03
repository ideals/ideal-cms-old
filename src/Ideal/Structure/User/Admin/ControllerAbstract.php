<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\User\Admin;

use Exception;
use Ideal\Core\Admin\Controller as AdminController;
use Ideal\Core\Request;
use Ideal\Structure\User;

/**
 * Класс, отвечающий за отображение списка пользователей в админке, а также
 * за отображение формы авторизации и её обработку
 */
class ControllerAbstract extends AdminController
{
    /**
     * {@inheritdoc}
     */
    public function finishMod($actionName)
    {
        if ($actionName === 'loginAction') {
            $this->view->header = '';
            $this->view->title = 'Вход в систему администрирования';
            $this->view->structures = [];
            $this->view->breadCrumbs = '';
        }
    }

    /**
     * Отображение списка пользователей
     */
    public function indexAction()
    {
        $this->templateInit();

        // Считываем список элементов
        $request = new Request();
        $page = (int)$request->page;

        $listing = $this->model->getListAcl($page);
        $headers = $this->model->getHeaderNames();

        $this->parseList($headers, $listing);

        $this->view->pager = $this->model->getPager('page');
    }

    /**
     * Отображение формы авторизации, если пользователь не авторизован
     * @throws \JsonException
     */
    public function loginAction()
    {
        // Проверяем что запрашивается json
        $jsonResponse = false;
        $pattern = '/.*json.*/i';
        if (preg_match($pattern, $_SERVER['HTTP_ACCEPT'])) {
            $jsonResponse = true;
        }

        // Если запрашивается не json и не html версия, то вероятнее всего это бот
        if (!$jsonResponse && !preg_match('/.*html.*/i', $_SERVER['HTTP_ACCEPT'])) {
            throw new Exception('Какой-то робот пытается зайти на страницу админки.');
        }

        $user = User\Model::getInstance();

        // Проверяем правильность логина и пароля
        if (isset($_POST['user'], $_POST['pass'])) {
            // При ajax авторизации отдаём json ответы
            if ($jsonResponse) {
                if ($user->login($_POST['user'], $_POST['pass'])) {
                    echo json_encode(['login' => 'true'], JSON_THROW_ON_ERROR);
                } else {
                    echo json_encode([
                        'errorResponse' => $user->errorMessage,
                        'login' => 'false'
                    ],JSON_THROW_ON_ERROR);
                }
                exit;
            }
            if ($user->login($_POST['user'], $_POST['pass'])) {
                header('Location: ' . $_SERVER['REQUEST_URI']);
            }
        } else {
            // На странице авторизации отдавать 404 заголовок
            $this->model->is404 = true;
        }

        // Если запрашивается json при не авторизованном пользователе
        // отдаём ответ инициализирующий показ формы авторизации
        if ($jsonResponse) {
            echo json_encode([
                'errorResponse' => 'not Login',
            ], JSON_THROW_ON_ERROR);
            exit;
        }

        $this->templateInit('login.twig');
        $this->view->message = $user->errorMessage;
    }

    /**
     * Экшен для вывода уведомления о запрещённом доступе к странице
     */
    public function accessDeniedAction()
    {
        $this->templateInit('access-denied.twig');
        $this->view->header = 'Доступ запрещён';
    }
}
