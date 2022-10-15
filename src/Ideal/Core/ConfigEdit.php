<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Core;

use JsonException;
use RuntimeException;

/**
 * Чтение, отображение и запись специального формата конфигурационных php-файлов
 */
class ConfigEdit
{

    /** @var array Массив для хранения считанных данных из php-файла */
    protected array $params = [];

    /**
     * Геттер для поля $params
     *
     * @return array Набор считанных из файла параметров
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Сеттер для поля $this->params
     *
     * @param array $params Модифицированный набор полей для сохранения в конфигурационном файле
     *
     * @return $this
     */
    public function setParams(array $params): self
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Сохранение обработанных конфигурационных данных в файл
     *
     * @param string $fileName Название php-файла, в который сохраняются данные
     *
     * @return bool|int Возвращает количество записанных в файл байт или false
     * @throws JsonException
     */
    public function saveFile(string $fileName)
    {
        // Изменяем постоянные настройки сайта
        $file = "<?php\n// @codingStandardsIgnoreFile\n/** @noinspection ALL */\nreturn [\n";

        $file .= $this->saveRecursive($this->getParams());

        return file_put_contents($fileName, $file, FILE_USE_INCLUDE_PATH);
    }

    /**
     * Считывание данных из php-файла
     *
     * @param string $fileName Имя php-файла из которого читается конфигурация
     *
     * @return bool Флаг успешного считывания данных из файла
     *
     * @throws JsonException
     */
    public function loadFile(string $fileName): bool
    {
        if (!stream_resolve_include_path($fileName)) {
            return false;
        }

        $file = fopen($fileName, 'rb');

        $this->params = $this->recursiveLoad($file, $fileName);

        return true;
    }

    /**
     * Рекурсивное считывание файла для последующего редактирования переменных
     *
     * @param resource $file Указатель на открытый файл
     * @param string $fileName Название файла (для сообщения об ошибке)
     *
     * @return array
     * @throws JsonException
     * @noinspection OffsetOperationsInspection
     */
    protected function recursiveLoad($file, string $fileName): array
    {
        $skip = [
            '<?php',
            '// @codingStandardsIgnoreFile',
            '/** @noinspection ALL */',
            'return [',
            '];'
        ];

        $params = [];

        // Проходимся по всем строчкам php-файла и заполняем массив $params
        while (!feof($file)) {
            $row = trim(fgets($file));
            if ($row === '' || in_array($row, $skip, true)) {
                continue;
            }
            if ($row === '],') {
                break;
            }
            if (mb_strpos($row, "', // ")) {
                $cols = explode("', // ", $row);
            } else {
                $cols = explode('", // ', $row);
            }
            $other = $cols[0];
            $label = $cols[1] ?? null;
            if ($label !== null) {
                // Считываем и записываем переменную первого уровня
                $param = $this->parseStr($row);
                $params[$param['name']] = $param;
                continue;
            }
            // Комментария в нужном формате нет, значит это массив
            preg_match('/\'(.*)\'\s*=>\s*\[\s*\/\/\s*(.*)/', $other, $match);
            if (!isset($match[1], $match[2])) {
                throw new RuntimeException(
                    sprintf('Ошибка парсинга файла %s в строке "%s"', $fileName, $row)
                );
            }
            $params[$match[1]] = [
                'name' => $match[1],
                'label' => $match[2],
                'array' => $this->recursiveLoad($file, $fileName)
            ];
        }

        return $params;
    }

    /**
     * Парсим одну строку конфига в массив данных
     *
     * @param string $str Строка конфига
     *
     * @return array
     *
     * @throws JsonException
     *
     * @noinspection OffsetOperationsInspection
     */
    protected function parseStr(string $str): array
    {
        $separator = strpos($str, "', // ") ? "', // " : '", // ';
        [$other, $label] = explode($separator, $str);

        $label = rtrim($label);
        $fields = explode(' | ', $label);
        $label = $fields[0];
        $type = $fields[1] === '' ? 'Ideal_Text' : $fields[1];

        $separator = strpos($other, " => '") ? " => '" : ' => "';
        [$name, $value] = explode($separator, $other);

        $value = str_replace('\n', "\n", $value); // заменяем переводы строки на правильные символы
        $fieldName = trim($name, ' \''); // убираем стартовые пробелы и кавычку у названия поля
        $param = [
            'name' => $fieldName,
            'label' => $label,
            'value' => $value,
            'type' => $type,
            'sql' => '',
        ];
        if ($type === 'Ideal_Select') {
            $param['values'] = json_decode($fields[2], true, 512, JSON_THROW_ON_ERROR);
        }

        return $param;
    }

    /**
     * @param array $params
     * @param int $pad
     *
     * @return string
     *
     * @throws JsonException
     */
    private function saveRecursive(array $params, int $pad = 4): string
    {
        $file = '';
        foreach ($params as $field => $param) {
            if (isset($param['array'])) {
                $file .= str_repeat(' ', $pad) . "'" . $field . "' => [ // " . $param['label'] . "\n";
                $file .= $this->saveRecursive($param['array'], $pad + 4);
                continue;
            }

            $values = '';
            if ($param['type'] === 'Ideal_Select') {
                $values = json_encode($param['values'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }

            // Экранируем переводы строки для сохранения в файле
            $param['value'] = str_replace(["\r", "\n"], ['', '\n'], $param['value']);

            $file .= str_repeat(' ', $pad) . "'" . $field . "' => " . '"' . $param['value'] . '", '
                . '// ' . $param['label'] . ' | ' . $param['type'] . $values . "\n";
        }

        $file .= $pad === 4 ? str_pad(' ', $pad - 4) . "];\n" : str_pad(' ', $pad - 4) . "],\n";

        return $file;
    }
}
