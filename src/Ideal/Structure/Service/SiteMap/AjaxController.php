<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Service\SiteMap;

use Exception;
use Ideal\Core\Config;
use Ideal\Core\Request;
use Ideal\Spider\Crawler;
use JsonException;
use RuntimeException;

class AjaxController extends \Ideal\Core\Admin\AjaxController
{
    /**
     * Запуск сбора карты сайта
     *
     * @throws Exception
     * @throws JsonException
     */
    public function runAction()
    {
        $config = Config::getInstance();
        $filePath = $config->rootDir . '/config/site_map.php';

        if (!file_exists($filePath)) {
            return 'Не найден файл конфигурации';
        }

        /** @noinspection UsingInclusionReturnValueInspection */
        $params = require $filePath;

        $request = new Request();

        $crawler = new Crawler(
            $params,
            (bool)$request->get('f'),
            (bool)$request->get('c'),
            true
        );

        ob_start();

        echo '<pre>';

        try {
            $crawler->run();
        } catch (RuntimeException $e) {
            echo $e->getMessage();
        }

        echo '</pre>';

        return ob_get_clean();
    }
}
