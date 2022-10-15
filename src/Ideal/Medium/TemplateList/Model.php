<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Medium\TemplateList;

use Exception;
use Ideal\Core\Config;
use Ideal\Core\Util;
use Ideal\Medium\AbstractModel;

/**
 * Медиум для получения списка шаблонов, которые можно выбрать для отображения структуры $obj
 */
class Model extends AbstractModel
{
    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function getList(): array
    {
        $config = Config::getInstance();

        $objClassName = get_class($this->obj); // определяем название класса модели редактируемого элемента
        $objClassNameSlice = (array)explode('\\', $objClassName);

        // Получаем название текущего типа структуры
        $modelStructures = [$objClassNameSlice[0] . '_' . $objClassNameSlice[2]];

        // Заносим уже введённое значение в список доступных шаблонов, так как оно может быть кастомным
        $pageData = $this->obj->getPageData();
        if (!empty($pageData['template'])) {
            $list[$modelStructures[0]][$pageData['template']] = $pageData['template'];
        }

        // Проверяем какие типы можно создавать в этом разделе
        if (isset($this->obj->params['structures']) && !empty($this->obj->params['structures'])) {
            // Учитываем все возможные типы из этого раздела при построении списка шаблонов для отображения
            $modelStructures = array_unique($this->obj->params['structures']);
        }

        // Получаем список структур, которые можно создавать в этой структуре
        $structures = [];
        foreach ($config->structures as $structure) {
            if (in_array($structure['structure'], $modelStructures, true)) {
                $structures[] = $structure['structure'];
            }
        }

        // Проходим по списку всех возможных типов из этого раздела и ищем в них шаблоны для отображения
        $list = [];
        foreach ($structures as $value) {
            $folderName = str_replace('\\', '/', Util::getClassName($value, 'Structure'));

            $folders = [
                '/src' . $folderName,
                '/vendor' . '/ideals/idealcms-old/src' . $folderName,
            ];

            foreach ($folders as $folder) {
                $twigTplRootScanFolder = $config->rootDir . $folder . '/Site/';

                // Проверяем на существование директорию перед сканированием.
                if (is_dir($twigTplRootScanFolder)) {
                    $nameTpl = '/\.twig$/';
                    $templates = scandir($twigTplRootScanFolder);

                    // Получаем список доступных для выбора шаблонов
                    foreach ($templates as $node) {
                        if (preg_match($nameTpl, $node)) {
                            $list[$value][$node] = $folder . '/Site/' . $node;
                        }
                    }
                }
            }
        }

        return $list;
    }
}
