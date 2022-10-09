<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2014 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\Template;

/**
 * Класс AjaxController предоставляет доступ к js-скрипту
 *
 */
class AjaxController extends \Ideal\Core\Admin\AjaxController
{
    /**
     * @return string
     */
    public function scriptAction(): string
    {
        $this->setContentType('application/javascript');

        return (string)file_get_contents(__DIR__ . '/templateShowing.js');
    }
}
