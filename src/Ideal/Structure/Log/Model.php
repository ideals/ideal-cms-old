<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Log;

use Ideal\Core\Config;
use Ideal\Core\Db;
use Ideal\Structure\User\Model as UserModel;
use JsonException;

/**
 * Класс обеспечивающий логирование
 */
class Model
{
    /**
     * @var string Название таблицы в базе
     */
    protected string $table;

    public function __construct()
    {
        $config = Config::getInstance();
        $this->table = $config->db['prefix'] . 'ideal_structure_log';
    }

    /**
     * Авария, система неработоспособна.
     *
     * @param string $message
     * @param array $context
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Тревога, меры должны быть предприняты незамедлительно.
     *
     * @param string $message
     * @param array $context
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * Критическая ошибка, критическая ситуация.
     *
     * @param string $message
     * @param array $context
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Ошибка на стадии выполнения, не требующая неотложного вмешательства,
     * но требующая протоколирования и дальнейшего изучения.
     *
     * @param string $message
     * @param array $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Предупреждение, нештатная ситуация, не являющаяся ошибкой.
     *
     * @param string $message
     * @param array $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Замечание, важное событие.
     *
     * @param string $message
     * @param array $context
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Информация, полезные для понимания происходящего события.
     *
     * @param string $message
     * @param array $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Информация, полезные для понимания происходящего события.
     *
     * @param string $message
     * @param array $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Протоколирование с произвольным уровнем.
     *
     * @param string $level Константа одного из уровней протоколирования
     * @param string $message
     * @param array $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $config = Config::getInstance();
        $db = Db::getInstance();
        $user = UserModel::getInstance();
        $json = [];

        if (isset($context['model'])) {
            $model = $context['model'];
            $structure = $config->getStructureByClass(get_class($model));
            $pageData = $model->getPageData();
            $json['structure_id'] = $structure['ID'];
            $json['element_id'] = $pageData['ID'];
        }

        // Генерируем преструктуру для записи в базу
        $par = ['structure' => 'Ideal_Log'];
        $fields = ['table' => $config->db['prefix'] . 'ideal_structure_datalist'];
        $result = $db->select(/** @lang text */
            'SELECT * FROM &table WHERE structure = :structure', $par, $fields);
        $id = $result[0]['ID'];
        $datalistStructure = $config->getStructureByName('Ideal_DataList');
        $prevStructure = $datalistStructure['ID'] . '-' . $id;

        try {
            $json = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } /** @noinspection BadExceptionsProcessingInspection */ catch (JsonException $e) {
            // todo логирование
        }

        $par = [
            'prev_structure' => $prevStructure,
            'date_create' => time(),
            'level' => $level,
            'user_id' => $user->data['ID'],
            'type' => $context['type'],
            'message' => $message,
            'json' => $json,
        ];

        $db->insert($this->table, $par);
    }
}
