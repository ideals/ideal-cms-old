<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2014 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\UrlAuto;

/**
 * Класс AjaxController предоставляет доступ к js-скрипту
 *
 */
class AjaxController extends \Ideal\Core\AjaxController
{
    public function scriptAction()
    {
        return file_get_contents(__DIR__ . '/script.js');
    }
}
