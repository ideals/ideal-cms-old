<?php

use Ideal\Core\Admin\Controller;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (RouteCollection $routes) {
    // todo переделать на работающий пример отдельного маршрута
    $route = new Route('/hello/{slug}', ['_controller' => Controller::class]);
    $routes->add('adminka', $route);

    return $routes;
};
