<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\User\Admin;

use Ideal\Core\Admin\Controller as AdminController;
use Ideal\Core\Request;
use Ideal\Core\View;
use Ideal\Structure\User;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Класс, отвечающий за отображение списка пользователей в админке, а также
 * за отображение формы авторизации и её обработку
 */
class ControllerAbstract extends AdminController
{
    /**
     * {@inheritdoc}
     */
    public function finishMod(string $actionName): void
    {
        if ($actionName === 'loginAction') {
            $this->view->set('header', '');
            $this->view->set('title', 'Вход в систему администрирования');
            $this->view->set('structures', []);
            $this->view->set('breadCrumbs', '');
        }
    }

    /**
     * Отображение списка пользователей
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

    /**
     * Отображение формы авторизации, если пользователь не авторизован
     *
     * @throws JsonException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function loginAction(): Response
    {
        // Проверяем что запрашивается json
        $jsonResponse = stripos($_SERVER['HTTP_ACCEPT'], 'json') !== false;

        // Если запрашивается не json и не html версия, то вероятнее всего это бот
        if (!$jsonResponse && stripos($_SERVER['HTTP_ACCEPT'], 'html') === false) {
            throw new RuntimeException('Какой-то робот пытается зайти на страницу админки.');
        }

        $user = User\Model::getInstance();

        $response = new Response();

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
                exit;
            }
        } else {
            // На странице авторизации отдавать 404 заголовок
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
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
        $this->view->set('message', $user->errorMessage);

        if ($this->model === null) {
            $this->model = new Model('');
            $this->view = new View('');
        }

        return $response;
    }

    /**
     * Экшен для вывода уведомления о запрещённом доступе к странице
     */
    public function accessDeniedAction(): void
    {
        $this->templateInit('access-denied.twig');
        $this->view->set('header', 'Доступ запрещён');
    }
}
