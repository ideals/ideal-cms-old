<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Error404;

use Exception;
use Ideal\Core\Config;
use Ideal\Core\Db;
use Ideal\Mailer;
use Ideal\Structure\Service\SiteData\ConfigPhp;
use Ideal\Structure\User;
use JsonException;

/**
 * Класс для обработки 404-ых ошибок
 */
class Model
{
    /** @var string Адрес запрошенной страницы */
    protected string $url = '';

    /** @var bool Флаг отправки сообщения о 404ой ошибке */
    protected bool $send404 = true;

    /** @var mixed Признак доступности файла со списком известных 404ых.
     * Содержит информацию из этого файла, в случае его доступности
     */
    protected $known404 = false;

    /**
     * Устанавливает адрес запрошенной страницы
     *
     * @param string $url Адрес запрошенной страницы
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * Возвращает значение флага отправки сообщения о 404ой ошибке
     */
    public function send404(): bool
    {
        return $this->send404;
    }

    /**
     * Сохраняет информацию о 404 ошибке в справочник/файл
     */
    public function save404(): void
    {
        $db = Db::getInstance();
        $config = Config::getInstance();
        $error404Structure = $config->getStructureByName('Ideal_Error404');
        $error404Table = $config->db['prefix'] . 'ideal_structure_error404';
        $user = new User\Model();
        $isAdmin = $user->checkLogin();
        $this->send404 = true;

        // Запускаем процесс обработки 404 страницы только если
        // существует структура "Ideal_Error404",
        // существует файл known404.php,
        // в настройках включена галка "Уведомление о 404ых ошибках",
        // пользователь не залогинен в админку
        if ($error404Structure !== false && $this->known404 !== false && !$isAdmin) {
            $known404Params = $this->known404->getParams();
            // Проверяем, есть ли запрошенный url среди исключений
            $rules404List = array_filter(explode("\n", $known404Params['rules']['arr']['rulesExclude404']['value']));
            $matchesRules = $this->matchesRules($rules404List, $this->url);
            if (empty($matchesRules)) {
                // Получаем данные о рассматриваемом url в справочнике "Ошибки 404"
                $par = ['url' => $this->url];
                $fields = ['table' => $error404Table];
                $rows = $db->select('SELECT * FROM &table WHERE BINARY url = :url LIMIT 1', $par, $fields);
                if (count($rows) === 0) {
                    // Добавляем запись в справочник
                    $dataList = $config->getStructureByName('Ideal_DataList');
                    $prevStructure = $dataList['ID'] . '-';
                    $par = ['structure' => 'Ideal_Error404'];
                    $fields = ['table' => $config->db['prefix'] . 'ideal_structure_datalist'];
                    $row = $db->select('SELECT ID FROM &table WHERE structure = :structure', $par, $fields);
                    $prevStructure .= $row[0]['ID'];
                    $params = [
                        'prev_structure' => $prevStructure,
                        'date_create' => time(),
                        'url' => $this->url,
                        'count' => 1,
                    ];
                    $db->insert($error404Table, $params);
                } elseif ($rows[0]['count'] < 15) {
                    $this->send404 = false;

                    // Увеличиваем счётчик посещения страницы
                    $values = ['count' => $rows[0]['count'] + 1];
                    $par = ['url' => $this->url];
                    $db->update($error404Table)->set($values)->where('url = :url', $par)->exec();
                } else {
                    $this->send404 = false;

                    // Переносим данные из справочника в файл с известными 404
                    $known404List = array_filter(explode("\n", $known404Params['known']['arr']['known404']['value']));
                    $known404List[] = $this->url;
                    $known404Params['known']['arr']['known404']['value'] = implode("\n", $known404List);
                    $this->known404->setParams($known404Params);
                    $this->known404->saveFile(DOCUMENT_ROOT . '/' . $config->cmsFolder . '/known404.php');
                    $par = ['url' => $this->url];
                    $db->delete($error404Table)->where('url = :url', $par)->exec();
                }
            }
        } elseif ($isAdmin) {
            // Если пользователь залогинен в админку, то удаляем запрошенный адрес из справочника "Ошибки 404"
            $par = ['url' => $this->url];
            $db->delete($error404Table)->where('url = :url', $par)->exec();
        }
    }

    /**
     * Фильтрует массив известных 404-ых или правил игнорирования по совпадению с запрошенным адресом
     *
     * @param array $rules Список правил с которыми сравнивается $url
     * @param string $url Запрошенный адрес
     * @return array Массив совпадений запрошенного адреса и известных 404-ых
     */
    private function matchesRules(array $rules, string $url): array
    {
        return array_filter($rules, static function ($rule) use ($url) {
            if (strncmp($rule, '/', 1) !== 0) {
                $rule = '/^' . addcslashes($rule, '/\\^$.[]|()?*+{}') . '$/';
            }
            return !empty($rule) && (preg_match($rule, $url));
        });
    }

    /**
     * Проверяем, входит ли указанный $url в массив известных 404-ых ошибок
     *
     * @param string $url Проверяемый url
     *
     * @return bool
     */
    public function isKnown404(string $url): bool
    {
        $config = Config::getInstance();

        $knownFile = DOCUMENT_ROOT . $config->cmsFolder . '/known404.php';
        $known404 = file_exists($knownFile) ? include($knownFile) : ['known' => ['known404' => '']];
        $known404 = explode("\n", $known404['known']['known404']);
        $is404 = $this->matchesRules($known404, $url);

        return !empty($is404);
    }

    /**
     * Отправка письма о 404-ой ошибке
     * @throws Exception
     */
    protected function emailError404(): void
    {
        $config = Config::getInstance();
        $sent404 = $config->cms['error404Notice'] ?? true;
        if ($sent404) {
            if (empty($_SERVER['HTTP_REFERER'])) {
                $from = 'Прямой переход.';
            } else {
                $from = 'Переход со страницы ' . $_SERVER['HTTP_REFERER'];
            }
            $message = "Здравствуйте!\n\nНа странице http://{$config->domain}{$_SERVER['REQUEST_URI']} "
                . "произошли следующие ошибки.\n\n"
                . "\n\nСтраница не найдена (404).\n\n"
                . "\n\n$from\n\n";
            $user = new User\Model();
            if ($user->checkLogin()) {
                $message .= "\n\nДействие совершил администратор.\n\n";
            }
            $message .= '$_SERVER = ' . "\n" . print_r($_SERVER, true) . "\n\n";
            $subject = 'Страница не найдена (404) на сайте ' . $config->domain;
            $mail = new Mailer();
            $mail->setSubj($subject);
            $mail->setPlainBody($message);
            $mail->sent($config->robotEmail, $config->cms['adminEmail']);
        }
    }
}
