<?php

use Ideal\Controller\CaptchaController;
use Ideal\Controller\MinifyController;
use Ideal\Controller\ResizeController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (RouteCollection $routes) {

    // Добавляем маршрут для изменения размеров изображений
    $route = new Route('/images/resized/{slug}', ['_controller' => ResizeController::class]);
    $route->setRequirements(['slug' => '.+']);
    $routes->add('resized', $route);

    // Объединение и минификация css
    $route = new Route('/css/all.min.css', ['_controller' => MinifyController::class, 'action' => 'css']);
    $routes->add('min.css', $route);

    // Объединение и минификация js
    $route = new Route('/js/all.min.js', ['_controller' => MinifyController::class, 'action' => 'js']);
    $routes->add('min.js', $route);

    // Отображение картинки капчи
    $route = new Route('/images/captcha.jpg', ['_controller' => CaptchaController::class, 'action' => 'image']);
    $routes->add('resized', $route);

    return $routes;
};
