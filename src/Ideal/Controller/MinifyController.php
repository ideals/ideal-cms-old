<?php

namespace Ideal\Controller;

use Ideal\Core\Config;
use MatthiasMullie\Minify\CSS as MinifyCss;
use MatthiasMullie\Minify\JS as MinifyJs;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MinifyController
{
    public function cssAction(Request $request): Response
    {
        $config = Config::getInstance();

        $cssFiles = $request->get('css', []);

        $minifier = new MinifyCss();

        foreach ($cssFiles as $file) {
            $file = trim($file);
            if (strncmp($file, 'http', 4) !== 0) {
                // Убираем лишние пробелы из путей и добавляем путь к корню сайта на диске
                $file = $config->publicDir . '/' . ltrim($file, '/');
                $minifier->add(file_get_contents($file));
            } else {
                $minifier->add($file);
            }
        }

        // Объединяем, минимизируем и записываем результат в файл /css/all.min.css
        $saveFile = $config->publicDir . '/css/all.min.css';
        $minifier->minify($saveFile);

        // Выводим объединённый и минимизированный результат
        $response = new Response();
        $response->headers->set('Content-type', 'text/css');

        return $response->setContent(file_get_contents($saveFile));
    }

    public function jsAction(Request $request): Response
    {
        $config = Config::getInstance();

        $jsFiles = $request->get('js');

        $minifier = new MinifyJs();

        foreach ($jsFiles as $file) {
            $file = trim($file);
            if (strncmp($file, 'http', 4) !== 0) {
                // Убираем лишние пробелы из путей и добавляем путь к корню сайта на диске
                $file = $config->publicDir . '/' . ltrim($file, '/');
                $minifier->add(file_get_contents($file));
            } else {
                $minifier->add($file);
            }
        }

        // Объединяем, минимизируем и записываем результат в файл /js/all.min.js
        $saveFile = $config->publicDir . '/js/all.min.js';
        $minifier->minify($saveFile);

        // Выводим объединённый и минимизированный результат
        $response = new Response();
        $response->headers->set('Content-type', 'application/javascript');

        return $response->setContent(file_get_contents($saveFile));
    }
}
