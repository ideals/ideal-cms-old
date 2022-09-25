<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core;

use Exception;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Twig\TemplateWrapper;

/**
 * Класс вида View, обеспечивающий отображение переданных в него данных
 * в соответствии с указанным twig-шаблоном
 */
class View
{

    /** @var TemplateWrapper */
    protected TemplateWrapper $template;

    /** @var Environment */
    protected Environment $templater;

    /** @var array Массив для хранения переменных, передаваемых во View */
    protected array $vars = [];

    /**
     * Инициализация шаблонизатора
     *
     * @param string|array $pathToTemplates Путь или массив путей к папкам, где лежат используемые шаблоны
     * @param bool $isCache
     *
     * @throws Exception
     */
    public function __construct($pathToTemplates, bool $isCache = false)
    {
        // Определяем корневую папку системы для подключения шаблонов из любой вложенной папки через их путь
        $config = Config::getInstance();
        $cmsFolder = $config->rootDir . '/' . $config->cmsFolder;

        // Папки от которых строится путь до шаблона
        $idealFolders = ['Ideal.c', 'Ideal', 'Mods.c', 'Mods'];
        foreach ($idealFolders as $k => $v) {
            if (file_exists($cmsFolder . '/' . $v)) {
                $idealFolders[$k] = $cmsFolder . '/' . $v;
            } else {
                unset($idealFolders[$k]);
            }
        }

        $pathToTemplates = is_string($pathToTemplates) ? [$pathToTemplates] : $pathToTemplates;

        //$pathToTemplates = array_merge(array($cmsFolder), $pathToTemplates, $idealFolders);

        $loader = new FilesystemLoader($pathToTemplates);

        $config = Config::getInstance();
        $params = [];
        if ($isCache) {
            $cachePath = DOCUMENT_ROOT . $config->cms['tmpFolder'] . '/templates';
            $params['cache'] = stream_resolve_include_path($cachePath);
            if ($params['cache'] === false) {
                if (mkdir($cachePath, 0777, true) || is_dir($cachePath)) {
                    $params['cache'] = stream_resolve_include_path($cachePath);
                } else {
                    Util::addError('Не удалось определить путь для кэша шаблонов: ' . $cachePath);
                    exit;
                }
            }
        }
        $this->templater = new Environment($loader, $params);
    }

    /**
     * Получение переменной View
     *
     * Передача по ссылке используется для того, чтобы в коде была возможность изменять значения
     * элементов массива, хранящегося во View. Например:
     *
     * $view->addonName[key]['content'] = 'something new';
     *
     * @param string $name Название переменной
     * @return mixed Переменная
     */
    public function &__get(string $name)
    {
        if (is_scalar($this->vars[$name])) {
            $property = $this->vars[$name];
        } else {
            $property = &$this->vars[$name];
        }
        return $property;
    }

    /**
     * Магический метод для проверки наличия запрашиваемой переменной
     *
     * @param string $name Название переменной
     * @return bool Инициализирована эта переменная или нет
     */
    public function __isset(string $name)
    {
        return isset($this->vars[$name]);
    }

    /**
     * Установка значения элемента, передаваемого во View
     *
     * @param string $name Название переменной
     * @param mixed $value Значение переменной
     */
    public function __set(string $name, $value)
    {
        $this->vars[$name] = $value;
    }

    /**
     * Загрузка в шаблонизатор файла с twig-шаблоном
     *
     * @param string $fileName Название twig-файла
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function loadTemplate(string $fileName): void
    {
        $this->template = $this->templater->load($fileName);
    }

    public function render(): string
    {
        return $this->template->render($this->vars);
    }

    /**
     * Чистит все файлы twig кэширования
     */
    public static function clearTwigCache($path = null): void
    {
        $config = Config::getInstance();
        $cachePath = $path ?? (DOCUMENT_ROOT . $config->cms['tmpFolder'] . '/templates');

        if ($objs = glob($cachePath . '/*')) {
            foreach ($objs as $obj) {
                is_dir($obj) ? self::clearTwigCache($obj) : unlink($obj);
            }
        }
        if (!empty($path)) {
            rmdir($cachePath);
        }
    }

    /**
     * Установка значений переменных шаблона
     *
     * @param array $vars
     * @return void
     */
    public function setVars(array $vars): void
    {
        $this->vars = $vars;
    }

    /**
     * Объединение уже созданных переменных с дополнительным списком переменных
     *
     * @param array $vars
     * @return void
     */
    public function mergeVars(array $vars): void
    {
        $this->vars = array_merge($this->vars, $vars);
    }
}
