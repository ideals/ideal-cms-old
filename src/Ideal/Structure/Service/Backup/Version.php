<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Service\Backup;

use Exception;
use RuntimeException;

class Version
{
    /** @var array Ответ, возвращаемый при ajax-вызове */
    protected array $answer = ['message' => [], 'error' => false, 'data' => null];

    /**
     * @return array
     * @throws Exception
     */
    public function getVersions(): array
    {
        return $this->getVersionFromReadme(['Ideal-CMS' => dirname(__DIR__, 5)]);
    }

    /**
     * Получение версий из Readme.md
     *
     * @param array $mods Массив состоящий из названий модулей и полных путей к ним
     * @return null|array Версии модулей или false в случае ошибки
     * @throws Exception
     * @noinspection MultipleReturnStatementsInspection
     */
    public function getVersionFromReadme(array $mods): ?array
    {
        // Получаем файл README.md для cms
        $mdFile = 'README.md';
        $version = [];
        foreach ($mods as $k => $v) {
            if (!file_exists($v . '/' . $mdFile)) {
                $this->addAnswer('Отсутствует файл ' . $v . '/' . $mdFile, 'error');
                return null;
            }
            $lines = file($v . '/' . $mdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false || count($lines) === 0) {
                $this->addAnswer('Не удалось получить версию из ' . $v . '/' . $mdFile, 'error');
                return null;
            }
            // Получаем номер версии из первой строки
            // Формат номера: пробел+v.+пробел+номер-версии+пробел-или-конец-строки
            preg_match_all('/\sv\.(\s*)(.*)(\s*)/i', $lines[0], $ver);
            // Если номер версии не удалось определить — выходим
            if (!isset($ver[2][0]) || ($ver[2][0] === '')) {
                $this->addAnswer('Ошибка при разборе строки с версией файла', 'error');
                return null;
            }

            $version[$k] = $ver[2][0];
        }

        return $version;
    }

    /**
     * Добавление сообщения, возвращаемого в ответ на ajax запрос
     *
     * @param string|array $message Сообщения возвращаемые в ответ на ajax запрос
     * @param string $type Статус сообщения, характеризующий так же наличие ошибки
     * @param mixed $data Данные передаваемые в ответ на ajax запрос
     * @throws Exception
     */
    public function addAnswer($message, string $type, $data = null): void
    {
        if (!is_string($message)) {
            throw new RuntimeException('Необходим аргумент типа строка');
        }
        if (!in_array($type, ['error', 'info', 'warning', 'success'])) {
            throw new RuntimeException('Недопустимое значение типа сообщения');
        }
        $this->answer['message'][] = [$message, $type];
        if ($type === 'error') {
            $this->answer['error'] = true;
        }
        if ($data !== null) {
            $this->answer['data'] = $data;
        }
    }

    /**
     * Получение результирующих данных
     *
     * @return array
     */
    public function getAnswer(): array
    {
        return $this->answer;
    }
}
