<?php

namespace Ideal\Controller;

use Ideal\Core\Config;
use MatthiasMullie\Minify\CSS as MinifyCss;
use MatthiasMullie\Minify\JS as MinifyJs;
use MatthiasMullie\Minify\Minify;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MinifyController
{
    public function cssAction(Request $request): Response
    {
        $config = Config::getInstance();

        $cssFiles = $request->get('css', []);

        $minifier = new MinifyCss();

        $this->loadContent($cssFiles, $minifier);

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

        $this->loadContent($jsFiles, $minifier);

        // Объединяем, минимизируем и записываем результат в файл /js/all.min.js
        $saveFile = $config->publicDir . '/js/all.min.js';
        $minifier->minify($saveFile);

        // Выводим объединённый и минимизированный результат
        $response = new Response();
        $response->headers->set('Content-type', 'application/javascript');

        return $response->setContent(file_get_contents($saveFile));
    }

    /**
     * @param array $files
     * @param Minify $minifier
     * @return void
     */
    protected function loadContent(array $files, Minify $minifier): void
    {
        $config = Config::getInstance();

        $context = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];

        foreach ($files as $file) {
            $file = trim($file);
            if (strncmp($file, 'http', 4) !== 0) {
                // Убираем лишние пробелы из путей и добавляем путь к корню сайта на диске
                $file = $config->publicDir . '/' . ltrim($file, '/');
                $minifier->add(file_get_contents($file));
            } else {
                // Считываем содержимое по ссылке
                $minifier->add(file_get_contents($file, true, stream_context_create($context)));
            }
        }
    }
}
