<?php
/**
 * Изменение размера изображения
 */
namespace Ideal\Resize;

use RuntimeException;

class Resize
{
    /** @var int $width Ширина нового изображения */
    protected int $width;

    /** @var int $height Высота нового изображения */
    protected int $height;

    /** @var null|array $color Цвет фона изображения */
    protected ?array $color = null;

    /** @var string $sizeDelimiter Разделитель значений размеров изображения */
    protected string $sizeDelimiter = 'x';

    /** @var string $fullNameOriginal Полное имя изображения с исходным размером */
    protected string $fullNameOriginal;

    /** @var string $fullNameResized Полное имя изображения с изменённым размером */
    protected string $fullNameResized;

    /** @var bool Флаг того, что происходит обработка локального файла или картинки с другого сервера */
    protected bool $isLocal = true;

    protected string $siteRoot;
    private string $resizedPath;
    private array $allowSizes;
    private int $imageSizeInBytes;

    public function __construct(string $siteRoot, string $resizedPath, array $allowSizes)
    {
        $this->siteRoot = $siteRoot;
        $this->resizedPath = $resizedPath;
        $this->allowSizes = $allowSizes;
    }

    /**
     * @param string $image Строка содержащая параметры требуемого изображения, а также путь к исходному изображению
     */
    public function resize(string $image): string
    {
        $image = (string) mb_ereg_replace('^' . $this->resizedPath, '', $image);
        $this->setImage($image);
        $rImage = $this->resizeImage();
        $this->saveImage($rImage, $image);

        return $rImage;
    }

    public function getHeaders(): array
    {
        $time = time();

        // Получение даты изменения оригинального файла
        if ($this->isLocal) {
            $time = filemtime($this->fullNameOriginal);
            touch($this->fullNameResized, $time);
        }

        // Вывод изображения
        $getInfo = (array) getimagesize($this->fullNameResized);

        return [
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $this->imageSizeInBytes,
            'Content-type' => $getInfo['mime'],
            'Last-Modified' => gmdate('D, d M Y H:i:s', $time) . ' GMT'
        ];
    }

    /**
     * @param string $image Строка содержащая параметры требуемого изображения, а также путь к исходному изображению
     * @noinspection BadExceptionsProcessingInspection
     */
    public function run(string $image): void
    {
        $image = (string) mb_ereg_replace('^' . $this->resizedPath, '', $image);

        try {
            $this->setImage($image);
            $rImage = $this->resizeImage();
            $image = str_replace(':', '', $image); // заменяем двоеточие в адресе (если это адрес)
            $this->saveImage($rImage, $image);
        } catch (RuntimeException $e) {
            // Отправка 404 ошибки
            header('HTTP/1.x 404 Not Found');
            exit;
        }

        $this->echoImage($rImage);
    }

    /**
     * Разбор параметров требуемого изображения и их проверка
     *
     * @param string $image Строка содержащая размеры требуемого изображения, а также путь к исходному изображению
     * @return void
     * @throws RuntimeException
     */
    protected function setImage(string $image): void
    {
        $matches = [];
        preg_match('/http(s?)\/(.*)/i', $image, $matches);
        if (!empty($matches[0])) {
            // Если указана ссылка на картинку на другом ресурсе
            $this->isLocal = false;
            $this->fullNameOriginal = preg_replace('/\//', '://', $matches[0], 1);
            $imgInfo = [str_replace('/' . $matches[0], '', $image)];
        } else {
            // Если указан путь к локальному файлу
            $imgInfo = (array) explode('/', $image);
        }

        // Получаем требуемые размеры нового изображения
        $imgSize = (array) explode($this->sizeDelimiter, $imgInfo[0]);

        // Проверяем существование необходимых параметров ширины и высоты
        if (isset($imgSize[1])) {
            $this->width = (int)$imgSize[0];
            $this->height = (int)$imgSize[1];
        } else {
            throw new RuntimeException('Не указаны высота и/или ширина картинки');
        }

        // Проверяем существование параметра цвет
        if (isset($imgSize[2])) {
            // Преобразование цвета фона
            $this->color = sscanf('#' . $imgSize[2], '#%2x%2x%2x');
        }

        // Заданы нулевые размеры для resize, такой картинки не бывает
        if ($this->width === 0 && $this->height === 0) {
            throw new RuntimeException('Высота и ширина не могут быть нулевыми');
        }

        // Проверяем являются ли новые размеры разрешёнными
        if (!$this->isAllowResize()) {
            throw new RuntimeException('Неразрешённый размер уменьшенного изображения');
        }

        unset($imgInfo[0]);

        if (!empty($this->fullNameOriginal)) {
            return;
        }

        /** @var string $imgPath Путь к исходному изображению */
        $this->fullNameOriginal = $this->siteRoot . '/' . implode('/', $imgInfo);
        // Проверяем, существует ли исходный файл
        if (!file_exists($this->fullNameOriginal)) {
            throw new RuntimeException('Файл ' . $this->fullNameOriginal . 'отсутствует на диске');
        }
    }

    /**
     * Проверка, входит ли требуемый размер в список разрешённых
     *
     * @return bool Истина, в случае наличия требуемого размера в списке разрешённых или отсутствия такого списка
     */
    protected function isAllowResize(): bool
    {
        // Проверяем существует ли список разрешённых размеров
        if ($this->allowSizes === []) {
            return false;
        }

        // Проверяем есть ли в списке разрешённых размеров изображений запрошенное
        return in_array($this->width . $this->sizeDelimiter . $this->height, $this->allowSizes, true);
    }

    /**
     * Изменение размера изображения
     *
     * @return string Данные изображения
     */
    protected function resizeImage(): string
    {
        $imageInfo = getimagesize($this->fullNameOriginal);
        if (!is_array($imageInfo)) {
            throw new RuntimeException('Не получилось проанализировать исходную картинку: ' . $this->fullNameOriginal);
        }

        switch ($imageInfo['mime']) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($this->fullNameOriginal);
                break;
            case 'image/png':
                $src = imagecreatefrompng($this->fullNameOriginal);
                break;
            case 'image/gif':
                $src = imagecreatefromgif($this->fullNameOriginal);
                break;
            default:
                $src = null;
        }

        if ($src === null) {
            throw new RuntimeException(
                'Тип изображения (' . $imageInfo['mime'] . ') не соответствует необходимому'
            );
        }

        // Пропорциональное изменение изображения по ширине и высоте
        if ($this->width === 0) {
            $this->width = round(($this->height * imagesx($src)) / imagesy($src));
        }
        if ($this->height === 0) {
            $this->height = round(($this->width * imagesy($src)) / imagesx($src));
        }

        // Проверка цвета фона
        $isSetColor = false;
        if (isset($this->color) && count($this->color) === 3) {
            $isSetColor = true;
        }

        $resDest = $this->width / $this->height;
        $resSrc = imagesx($src) / imagesy($src);
        if ($resDest < $resSrc) {
            $destWidth = round(imagesx($src) * $this->height / imagesy($src));
            $dest2 = $this->imageCreate($this->width, $this->height, $imageInfo['mime']);

            if ($isSetColor) {
                // Изменение размера изображения с добавлением цвета фона
                $destHeight = round(($this->width * imagesy($src)) / imagesx($src));
                $destHeight2 = ($this->height - $destHeight) / 2;
                $bgColor = imagecolorallocate($dest2, $this->color[0], $this->color[1], $this->color[2]);
                imagefill($dest2, 0, 0, $bgColor);
                if ($destWidth > $this->width) {
                    $destWidth = $this->width;
                }
                imagecopyresampled(
                    $dest2,
                    $src,
                    0,
                    $destHeight2,
                    0,
                    0,
                    $destWidth,
                    $destHeight,
                    imagesx($src),
                    imagesy($src)
                );
            } else {
                // Изменение размера изображения и обрезка по ширине
                $dest = $this->imageCreate($destWidth, $this->height, $imageInfo['mime']);
                $destWidth2 = ($destWidth - $this->width) / 2;
                imagecopyresampled($dest, $src, 0, 0, 0, 0, $destWidth, $this->height, imagesx($src), imagesy($src));
                imagecopy($dest2, $dest, 0, 0, $destWidth2, 0, imagesx($dest), imagesy($dest));
            }
        } else {
            $destHeight = round(imagesy($src) * $this->width / imagesx($src));
            $dest2 = $this->imageCreate($this->width, $this->height, $imageInfo['mime']);

            if ($isSetColor) {
                // Изменение размера изображения с добавлением цвета фона
                $destWidth = round(($this->height * imagesx($src)) / imagesy($src));
                $destWidth2 = ($this->width - $destWidth) / 2;
                $bgColor = imagecolorallocate($dest2, $this->color[0], $this->color[1], $this->color[2]);
                imagefill($dest2, 0, 0, $bgColor);
                if ($destHeight > $this->height) {
                    $destHeight = $this->height;
                }
                imagecopyresampled(
                    $dest2,
                    $src,
                    $destWidth2,
                    0,
                    0,
                    0,
                    $destWidth,
                    $destHeight,
                    imagesx($src),
                    imagesy($src)
                );
            } else {
                // Изменение размера изображения и обрезка по высоте
                $dest = $this->imageCreate($this->width, $destHeight, $imageInfo['mime']);
                $destHeight2 = ($destHeight - $this->height) / 2;
                imagecopyresampled($dest, $src, 0, 0, 0, 0, $this->width, $destHeight, imagesx($src), imagesy($src));
                imagecopy($dest2, $dest, 0, 0, 0, $destHeight2, imagesx($dest), imagesy($dest));
                imagedestroy($dest);
            }
        }

        ob_start();
        switch ($imageInfo['mime']) {
            case 'image/jpeg':
                // 98 - соответствует по размеру файла тому, что генерирует Photoshop с качеством 80
                // 83 - оптимальное качество согласно PageSpeed Insights
                imagejpeg($dest2, null, 83);
                break;
            case 'image/png':
                imagepng($dest2, null, 1);
                break;
            case 'image/gif':
                imagegif($dest2);
                break;
        }
        $image = ob_get_clean();

        if ($image === '') {
            throw new RuntimeException('Не удалось создать изображение');
        }

        $this->imageSizeInBytes = strlen($image);

        return $image;
    }

    /**
     * Создание нового полноцветного изображения
     *
     * @param int $width Ширина нового изображения
     * @param int $height Высота нового изображения
     * @param string $mime Тип файла
     * @return resource Идентификатор изображения
     */
    protected function imageCreate(int $width, int $height, string $mime)
    {
        $img = imagecreatetruecolor($width, $height);

        if (is_bool($img)) {
            throw new RuntimeException('Не удалось начать обработку изображения');
        }

        if ($mime === 'image/png') {
            imagecolortransparent($img, imagecolorallocatealpha($img, 0, 0, 0, 127));
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        return $img;
    }

    /**
     * @param string $rImage Изображение в бинарном виде
     * @param string $originalImagePath Путь к оригиналу изображения
     */
    protected function saveImage(string $rImage, string $originalImagePath): void
    {
        /** @var string $pathResizedImg Путь к новому изображению */
        $resizedImgFile = $this->siteRoot . $this->resizedPath . $originalImagePath;
        $pathResizedImg = dirname($resizedImgFile);

        /** Добавляем структуру категорий */
        if (!file_exists($pathResizedImg) && !mkdir($pathResizedImg, 0777, true) && !is_dir($pathResizedImg)) {
            throw new RuntimeException(sprintf('Невозможно создать папку "%s"', $pathResizedImg));
        }

        $this->fullNameResized = $resizedImgFile;

        file_put_contents($this->fullNameResized, $rImage);
    }

    /**
     * Вывод изображения
     *
     * @param mixed $image Изображение в бинарном виде
     */
    protected function echoImage($image): void
    {
        foreach ($this->getHeaders() as $key => $value) {
            header($key . ': ' . $value);
        }

        echo($image);
    }
}
