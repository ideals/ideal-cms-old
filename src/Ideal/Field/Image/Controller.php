<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Field\Image;

use Exception as ExceptionAlias;
use Ideal\Controller\ResizeController;
use Ideal\Core\Config;
use Ideal\Field\AbstractController;

/**
 * Поле редактирования картинки
 *
 * В поле редактирования присутствует картинка и кнопка выбора/загрузки картинки с сервера
 *
 * Пример объявления в конфигурационном файле структуры:
 *     'img' => array(
 *         'label' => 'Картинка',
 *         'sql'   => 'varchar(255)',
 *         'type'  => 'Ideal_Image'
 *     ),
 */
class Controller extends AbstractController
{

    /** {@inheritdoc} */
    protected static $instance;

    /**
     * {@inheritdoc}
     * @throws ExceptionAlias
     */
    public function getInputText(): string
    {
        $value = htmlspecialchars($this->getValue());
        $startupPath = '';
        if (empty($value)) {
            $img = '<span class="glyphicon glyphicon-remove" id="' . $this->htmlName . 'Span"></span>'
                . '<img id="' . $this->htmlName . 'Img" src="" style="max-height:32px;display:none;" alt="">';
        } else {
            $img = '<img id="' . $this->htmlName . 'Img" src="' . $value . '" style="max-height:32px">';
            $startupPath = substr(dirname($value), strpos($value, '/', 2)) . '/';
        }
        return '<div class="input-group">'
        . '<span class="input-group-addon" style="padding: 0 5px">'
        // миниатюра картинки
        . $img . '</span>'
        . '<input type="text" class="form-control" name="' . $this->htmlName
        . '" id="' . $this->htmlName
        . '" value="' . $value
        . '" onchange="$(\'#' . $this->htmlName . 'Img\').show().attr(\'src\', $(this).val());$(\'#' . $this->htmlName . 'Span\').hide()">' // замена миниатюры картинки
        . '<span class="input-group-btn">'
        . '<button class="btn" onclick="showFinder(\'' . $this->htmlName . '\', \'Images\', \'' . $startupPath . '\'); return false;" >Выбрать</button>'
        . '</span></div>';
    }

    /**
     * {@inheritdoc}
     * @throws ExceptionAlias
     */
    public function parseInputValue(bool $isCreate): array
    {
        $item = parent::parseInputValue($isCreate);

        // Удаляем resized-варианты старой картинки
        $value = $this->getValue();
        $item['message'] .= $this->imageRegenerator($value);

        // Удаляем resized-варианты новой картинки
        $value = $this->pickupNewValue();
        $item['message'] .= $this->imageRegenerator($value);

        return $item;
    }

    /**
     * Удаление resized-вариантов картинки
     *
     * @param string $value
     * @return string
     * @noinspection MultipleReturnStatementsInspection
     */
    protected function imageRegenerator(string $value): string
    {
        $config = Config::getInstance();
        if ($value === '' || $config->allowResize === '') {
            return '';
        }

        // Из `.htaccess` определяем папку с resized-изображениями
        $resizer = new ResizeController();
        $folder = DOCUMENT_ROOT . '/' . $resizer->getResizedFolder();

        // Удаляем старое изображение из resized-папок
        $allowResize = explode('\n', $config->allowResize);
        foreach ($allowResize as $v) {
            $fileName = $folder . '/' . $v . $value;
            if (!file_exists($fileName)) {
                continue;
            }
            if (!is_writable($fileName)) {
                return 'Не могу удалить файл старой resized-картинки ' . $fileName;
            }
            unlink($fileName);
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getValueForList(array $values, string $fieldName): string
    {
        $result = '';
        if ($values[$fieldName] !== '') {
            $result = <<<HTML
<span
    class="has-popover"
    data-placement="top"
    data-content="<img src='$values[$fieldName]' width='200'>"
    data-html="true"
    data-trigger="hover">
        <span class="glyphicon glyphicon-camera text-muted"></span>
</span>
HTML;
        }
        return $result;
    }
}
