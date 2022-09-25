<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\User\Admin;

use Ideal\Core\Admin\Router;
use Ideal\Structure;

class Plugin
{
    public function onPostAdminDispatch(Router $router): void
    {
        // Регистрируем объект пользователя
        $user = Structure\User\Model::getInstance();

        // Инициализируем объект запроса
        $request = $router->getRequest();

        $_SESSION['IsAuthorized'] = true;

        // Если пользователь не залогинен — запускаем модуль авторизации
        if (!$user->checkLogin()) {
            $_SESSION['IsAuthorized'] = false;
            $request->query->set('action', 'login');
            $router->setControllerName(Controller::class);
        }
    }
}