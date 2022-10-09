<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2022 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Setup;

use Composer\Script\Event;
use Ideal\Core\Config;
use Ideal\Core\ConfigEdit;
use Ideal\Core\Db;
use JsonException;
use mysqli_result;
use RuntimeException;

class PostCreateProject
{
    private ConfigEdit $cmsConfig;

    private string $rootDir;

    private string $vendorDir;

    private Event $event;

    /**
     * @param string $vendorDir
     * @param Event $event
     *
     * @throws JsonException
     */
    public function __construct(string $vendorDir, Event $event)
    {
        $this->vendorDir = $vendorDir;
        $this->event = $event;

        $this->rootDir = stream_resolve_include_path($vendorDir . '/..');

        $this->cmsConfig = new ConfigEdit();

        $file = $this->vendorDir . '/' . Config::COMPOSER . '/config/cms.php';

        if (!$this->cmsConfig->loadFile($file)) {
            throw new RuntimeException('Отсутствует файл ' . $file);
        }
    }

    /**
     * @return void
     *
     * @throws JsonException
     */
    public function run(): void
    {
        $io = $this->event->getIO();

        $domain = trim($io->ask('Domain name [example.com]: ', 'example.com'));

        $folder = trim(trim($io->ask('Public folder [public_html]: ', 'public_html')), '/');
        $wwwDir = $this->rootDir . '/' . $folder;

        if (!file_exists($wwwDir) && !mkdir($wwwDir) && !is_dir($wwwDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $wwwDir));
        }

        $adminFolder = trim($io->ask('Admin folder [adminka]: ', 'adminka'));

        $dbHost = trim($io->ask('Database host [localhost]: ', 'localhost'));
        $dbUser = trim($io->ask('Database user: '));
        $dbPass = trim($io->ask('Database user password: '));
        $dbName = trim($io->ask('Database name: '));
        $dbPrefix = trim($io->ask('Prefix for CMS tables [i_]: ', 'i_'));

        $adminLogin = trim($io->ask('Administrator email: '));
        $adminPass = trim($io->ask('Administrator password: '));

        $domainFrom = mb_strpos($domain, 'www.') === false ? 'www.' . $domain : mb_eregi_replace('^www\.', '', $domain);
        $siteName = mb_strpos($domain, 'www.') === false ? $domain : $domainFrom;

        $placeholder = [
            'DOMAIN_FROM' => $domainFrom,
            'DOMAIN_TO' => $domain,
            'DOMAIN_FROM_ESC' => str_replace('.', '\.', $domain),
            'DB_HOST' => $dbHost,
            'DB_LOGIN' => $dbUser,
            'DB_PASS' => $dbPass,
            'DB_NAME' => $dbName,
            'DB_PREFIX' => $dbPrefix,
            'SITE_NAME' => $siteName,
            'CMS_LOGIN' => $adminLogin,
        ];

        $configFolder = $this->rootDir . '/config';

        $this->copyFile(
            $this->vendorDir . '/' . Config::COMPOSER . '/config/structure.php',
            $configFolder . '/structure.php'
        );

        $this->copyFile(
            $this->vendorDir . '/' . Config::COMPOSER . '/front/site/css/bootstrap.css',
            $this->rootDir . '/' . $folder . '/css/bootstrap.css'
        );

        $this->copyFile(
            $this->vendorDir . '/' . Config::COMPOSER . '/front/site/css/default.css',
            $this->rootDir . '/' . $folder . '/css/default.css'
        );

        $this->copyFile(
            $this->vendorDir . '/' . Config::COMPOSER . '/front/site/js/default.js',
            $this->rootDir . '/' . $folder . '/js/default.js'
        );

        $this->modifyFile('config/db.php', 'config/db.php', $placeholder);
        $this->modifyFile('config/site.php', 'config/site.php', $placeholder);
        $this->modifyFile('config/routes.php', 'config/routes.php', $placeholder);
        $this->modifyFile('front/.htaccess', $folder . '/.htaccess', $placeholder);
        $this->modifyFile('front/_.php', $folder . '/_.php', $placeholder);

        $params = $this->cmsConfig->getParams();
        $params['cms']['array']['publicFolder']['value'] = $folder;
        $params['domain']['value'] = $domain;
        $params['cmsFolder']['value'] = $adminFolder;
        $this->cmsConfig->setParams($params);

        $this->cmsConfig->saveFile($configFolder . '/cms.php');

        $this->createTables($adminLogin, $adminPass);

        $update = new PostUpdate($this->vendorDir, $this->event);
        $update->run();
    }

    private function modifyFile(string $fileFrom, string $fileTo, array $placeholder): void
    {
        $fileFrom = $this->vendorDir . '/' . Config::COMPOSER . '/' . $fileFrom;
        $fileTo = $this->rootDir . '/' . $fileTo;
        $content = file_get_contents($fileFrom);

        $content = str_replace(
            array_map(
                static function ($a) {
                    return '[[' . $a . ']]';
                },
                array_keys($placeholder)
            ),
            $placeholder,
            $content
        );

        file_put_contents($fileTo, $content);
    }

    /**
     * Копирует файл из $from в $to (пути абсолютные)
     *
     * @param string $from Источник копирования
     * @param string $to Цель копирования
     *
     * @return void
     */
    protected function copyFile(string $from, string $to): void
    {
        $toDir = dirname($to);

        if (!file_exists($toDir) && !mkdir($toDir, 0777, true) && !is_dir($toDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $toDir));
        }
        copy($from, $to);
    }

    private function createTables(string $cmsLogin, string $cmsPass): void
    {
        $config = Config::getInstance();
        $config->loadSettings($this->rootDir);

        $db = Db::getInstance();

        // Проверяем наличие таблиц с заданным префиксом
        /** @var MySQLi_Result $result */
        $result = $db->query("SHOW TABLES LIKE '" . $db->escape_string($config->db['prefix']) . "%'");
        if ($result->num_rows > 0) {
            throw new RuntimeException('<strong>Ошибка</strong>. В базе данных уже есть таблицы CMS '
                . 'с префиксом ' . htmlspecialchars($config->db['prefix']));
        }

        // Создаём таблицы аддонов
        foreach ($config->addons as $v) {
            $table = $config->getTableByName($v['structure'], 'addon');
            $className = $config->getStructureClass($v['structure'], 'Config', 'Addon');
            $cfg = new $className();
            if ($cfg::$fields !== []) {
                $db->create($table, $cfg::$fields);
            }
        }

        // Создаём таблицы связей
        foreach ($config->mediums as $v) {
            $table = $config->getTableByName($v['structure'], 'medium');
            $className = $config->getStructureClass($v['structure'], 'Config', 'Medium');
            $cfg = new $className();
            if ($cfg::$fields !== []) {
                $db->create($table, $cfg::$fields);
            }
        }

        // Устанавливаем всё что нужно для работы структур
        foreach ($config->structures as $v) {
            $installClassName = $config->getStructureClass($v['structure'], 'Install');
            if (class_exists($installClassName)) {
                $install = new $installClassName();
                $install->run();
            }
        }

        // Создаём пользователя админки
        $db->insert(
            $config->db['prefix'] . 'ideal_structure_user',
            [
                'email' => $cmsLogin,
                'reg_date' => time(),
                'password' => password_hash($cmsPass, PASSWORD_DEFAULT),
                'is_active' => 1,
                'prev_structure' => '0-2'
            ]
        );
    }
}
