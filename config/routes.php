<?php

use Ideal\Controller\ResizeController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (RouteCollection $routes) {

    // Добавляем маршрут для изменения размеров изображений
    $route = new Route('/images/resized/{slug}', ['_controller' => ResizeController::class]);
    $route->setRequirements(['slug' => '.+']);
    $routes->add('resized', $route);

    return $routes;
};
