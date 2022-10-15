<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\Service\CheckDb;

use Ideal\Core\Config;
use Ideal\Core\Db;

class Action
{
    public function render(): string
    {
        $result = <<<HTML
<ul class="nav nav-tabs">

    <li class="active"><a href="#bd" data-toggle="tab">База данных</a></li>
    <li><a href="#cache" data-toggle="tab">Кэш</a></li>
    <li><a href="#cmsFiles" data-toggle="tab">Файлы CMS</a></li>

</ul>

<div class="tab-content">

    <div class="tab-pane well active" id="bd">

        <form method="POST" action="">
HTML;

        $db = Db::getInstance();
        $config = Config::getInstance();

        $resultDb = $db->select('SHOW TABLES');

        $dbTables = [];
        foreach ($resultDb as $v) {
            $table = array_shift($v);

            // Получаем информацию о полях таблицы
            $fieldsInfo = $db->select('SHOW COLUMNS FROM ' . $table . ' FROM `' . $config->db['name'] . '`');
            $fields = [];
            array_walk($fieldsInfo, static function ($v) use (&$fields) {
                $fields[$v['Field']] = $v['Type'];
            });
            if (strpos($table, $config->db['prefix']) === 0) {
                $dbTables[$table] = $fields;
            }
        }

        $cfgTables = [];
        $cfgTablesFull = [];
        foreach ($config->structures as $v) {
            if (!$v['hasTable']) {
                continue;
            }
            $table = $config->getTableByName($v['structure']);
            $fields = $this->getFields($v);
            $cfgTablesFull[$table] = $fields;
            $cfgTables[$table] = $this->getFieldListWithTypes($fields);
        }

        foreach ($config->addons as $v) {
            if (!$v['hasTable']) {
                continue;
            }
            $table = $config->getTableByName($v['structure'], 'Addon');
            $fields = $this->getFields($v, 'Addon');
            $cfgTablesFull[$table] = $fields;
            $cfgTables[$table] = $this->getFieldListWithTypes($fields);
        }

        foreach ($config->mediums as $v) {
            if (!$v['hasTable']) {
                continue;
            }
            $table = $config->getTableByName($v['structure'], 'Medium');
            $fields = $this->getFields($v, 'Medium');
            $cfgTablesFull[$table] = $fields;
            $cfgTables[$table] = $this->getFieldListWithTypes($fields);
        }

        // Если есть таблицы, которые надо создать
        if (isset($_POST['create'])) {
            foreach ($_POST['create'] as $table => $v) {
                $result .= '<p>Создаём таблицу ' . $table . '…';
                $db->create($table, $cfgTablesFull[$table]);
                $result .= ' Готово.</p>';
                $dbTables[$table] = $cfgTables[$table];
            }
        }

        // Если есть поля, которые надо создать
        if (isset($_POST['create_field'])) {
            foreach ($_POST['create_field'] as $tableField => $v) {
                [$table, $field] = explode('-', $tableField);
                $result .= '<p>Добавляем поле ' . $field . ' в таблицу ' . $table . '…';
                $data = $cfgTablesFull[$table];

                // Поиск поля после которого нужно вставить новое
                $afterThisField = '';
                foreach ($data as $key => $value) {
                    $value['sql'] = trim($value['sql']);
                    if (($key !== $field) && !empty($value['sql'])) {
                        $afterThisField = $key;
                    } else {
                        break;
                    }
                }

                if (!empty($afterThisField)) {
                    $afterThisField = ' AFTER ' . $afterThisField;
                } else {
                    $afterThisField = ' FIRST';
                }

                // Составляем sql запрос для вставки поля в таблицу
                $sql = "ALTER TABLE $table ADD $field {$data[$field]['sql']}"
                    . " COMMENT '{$data[$field]['label']}' $afterThisField;";
                $db->query($sql);
                $result .= ' Готово.</p>';
                $fields = $this->getFieldListWithTypes($data);
                $dbTables[$table][$field] = $cfgTables[$table][$field];
            }
        }

        // Если есть таблицы, которые надо удалить
        if (isset($_POST['delete'])) {
            foreach ($_POST['delete'] as $table => $v) {
                $result .= '<p>Удаляем таблицу ' . $table . '…';
                $db->query("DROP TABLE `$table`");
                $result .= ' Готово.</p>';
                unset($dbTables[$table]);
            }
        }

        // Если есть поля, которые нужно удалить
        if (isset($_POST['delete_field'])) {
            foreach ($_POST['delete_field'] as $tableField => $v) {
                [$table, $field] = explode('-', $tableField);
                $result .= '<p>Удаляем поле ' . $field . ' в таблице ' . $table . '…';
                $db->query("ALTER TABLE $table DROP COLUMN $field;");
                $result .= ' Готово.</p>';
                unset($dbTables[$table][$field]);
            }
        }

        // Если есть поля, которые нужно преобразовать
        if (isset($_POST['change_type'])) {
            foreach ($_POST['change_type'] as $tableField => $v) {
                [$table, $field, $type] = explode('-', $tableField, 3);
                $result .= '<p>Изменяем поле ' . $field . ' в таблице ' . $table . ' на тип' . $type . '…';
                // Поле с типом "SET", требует особенного подхода в обновлении значений
                if (strncasecmp($type, 'set', 3) === 0) {
                    $db->query("ALTER TABLE $table CHANGE $field $field $type;");
                } else {
                    $db->query("ALTER TABLE $table MODIFY $field $type;");
                }
                $result .= ' Готово.</p>';
                $dbTables[$table][$field] = $type;
            }
        }

        $isCool = true;

        foreach ($cfgTables as $tableName => $tableFields) {
            if (!array_key_exists($tableName, $dbTables)) {
                $result .= '<p class="well"><input type="checkbox" name="create[' . $tableName . ']">&nbsp; ';
                $result .= 'Таблица <strong>' . $tableName . '</strong> отсутствует в базе данных. Создать?</p>';
                $isCool = false;
                continue;
            }

            // Получаем массив полей, которые нужно предложить создать
            $onlyConfigExist = array_diff_key($tableFields, $dbTables[$tableName]);

            // Предлагать создавать нужно только те поля, у которых определён sql тип.
            $onlyConfigExist = array_filter($onlyConfigExist);

            // Если какое-либо поле присутствует только в конфигурационном файле, то предлагаем его создать
            if (count($onlyConfigExist) > 0) {
                foreach ($onlyConfigExist as $missingField => $missingFieldType) {
                    $result .= '<p class="well">';
                    $result .= '<input type="checkbox" name="create_field[' . $tableName . '-' . $missingField . ']">&nbsp; ';
                    $result .= 'В таблице <strong>' . $tableName . '</strong> ';
                    $result .= 'отсутствует поле <strong>' . $missingField . '</strong>. Создать?</p>';
                }
                $isCool = false;
            }

            // Получаем массив полей, которые нужно предложить удалить
            $onlyBaseExist = array_diff_key($dbTables[$tableName], $tableFields);

            // Если какое-либо поле присутствует только в базе данных, то предлагаем его удалить
            if (count($onlyBaseExist) > 0) {
                foreach ($onlyBaseExist as $excessField => $excessFieldType) {
                    $result .= '<p class="well">';
                    $result .= '<input type="checkbox" name="delete_field[' . $tableName . '-' . $excessField . ']">&nbsp; ';
                    $result .= 'Поле <strong>' . $excessField . '</strong> ';
                    $result .= 'отсутствует в конфигурации таблицы <strong>' . $tableName . '</strong>. Удалить?</p>';
                }
                $isCool = false;
            }

            $fieldTypeDiff = $this->diffConfigBaseType($tableFields, $dbTables[$tableName]);
            // Если есть расхождение в типах полей, то предлагаем вернуть всё к виду конфигурационных файлов
            if (count($fieldTypeDiff) > 0) {
                foreach ($fieldTypeDiff as $fieldName => $typeDiff) {
                    $result .= '<p class="well">'
                        . '<input type="checkbox" '
                        . 'name="change_type[' . $tableName . '-' . $fieldName . '-' . $typeDiff['conf'] . ']">&nbsp; '
                        . 'Поле <strong>' . $fieldName . '</strong> в таблице <strong>' . $tableName . ' </strong> '
                        . 'имеет тип <strong>' . $typeDiff['base'] . '</strong>, '
                        . 'но в конфигурационном файле это поле определено типом '
                        . '<strong>' . $typeDiff['conf'] . '</strong>. Преобразовать поле в базе данных?</p>';
                }
                $isCool = false;
            }

            // Удаляем имеющиеся в конфигурации таблицы из списка таблиц в базе
            unset($dbTables[$tableName]);
        }

        foreach ($dbTables as $tableName => $tableFields) {
            $result .= '<p class="well"><input type="checkbox" name="delete[' . $tableName . ']">&nbsp;';
            $result .= 'Таблица <strong>' . $tableName . '</strong> отсутствует в конфигурации. Удалить?</p>';
            $isCool = false;
        }

        // После нажатия на кнопку применить и совершения действий, нужно либо заново перечитывать БД, либо перегружать страницу
        if (isset($_POST['create']) || isset($_POST['delete']) || isset($_POST['create_field'])
            || isset($_POST['delete_field']) || isset($_POST['change_type'])) {
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        if ($isCool) {
            $result .= 'Конфигурация в файлах соответствует конфигурации базы данных.';
        } else {
            $result .= '<button class="btn btn-primary btn-large" type="submit">Применить</button>';
        }

        $result .= <<<HTML
</form>
</div>
<style type="text/css">
    #iframe {
        margin-top: 15px;
    }

    #loading {
        -webkit-animation: loading 3s linear infinite;
        animation: loading 3s linear infinite;
    }

    @-webkit-keyframes loading {
        0% {
            color: rgba(34, 34, 34, 1);
        }
        50% {
            color: rgba(34, 34, 34, 0);
        }
        100% {
            color: rgba(34, 34, 34, 1);
        }
    }

    @keyframes loading {
        0% {
            color: rgba(34, 34, 34, 1);
        }
        50% {
            color: rgba(34, 34, 34, 0);
        }
        100% {
            color: rgba(34, 34, 34, 1);
        }
    }
</style>

<div class="tab-pane well" id="cache">
    <button class="btn btn-info" value="Удаление файлов" onclick="clearCacheFiles()">
        Удаление файлов
    </button>
</div>

<div class="tab-pane well" id="cmsFiles">
    <button class="btn btn-info" value="Проверка целостности файлов" onclick="checkCmsFiles()">
        Проверка целостности файлов
    </button>
    <span id="loading"></span>
    <div id="iframe">
    </div>
</div>


<script>
    function clearCacheFiles()
    {
        var text = '';
        $.ajax({
            url: '',
            data: {action: 'clearCacheFiles', controller: 'Ideal\\\Structure\\\Service\\\Cache', mode: 'ajax'},
            success: function (data)
            {
                if (data.text) {
                    text = 'Удалённые файлы кэша: <br />' + data.text;
                }
                else{
                    text = 'Информация о закэшированных страницах верна.';
                }
                $('.nav-tabs').parent().prepend('<div class="alert alert-block alert-success fade in">'
                    + '<button type="button" class="close" data-dismiss="alert">&times;</button>'
                    + '<span class="alert-heading">' + text + '</span></div>');
            },
            type: 'GET',
            dataType: "json"
        });
    }

    function checkCmsFiles()
    {
        $('#loading').html('Идёт сбор данных. Ждите.');
        $('#iframe').html('');
        var text = '';
        $.ajax({
            url: '',
            data: {action: 'checkCmsFiles', controller: 'Ideal\\\Structure\\\Service\\\CheckCmsFiles', mode: 'ajax'},
            success: function (data)
            {
                if (data.newFiles) {
                    text += 'Были добавлены новые файлы: <br />' + data.newFiles;
                }
                if (data.changeFiles) {
                    if (text != '') {
                        text += '<br /><br />';
                    }
                    text += 'Были внесены изменения в следующие файлы: <br />' + data.changeFiles;
                }
                if (data.delFiles) {
                    if (text != '') {
                        text += '<br /><br />';
                    }
                    text += 'Были удалены следующие файлы: <br />' + data.delFiles;
                }
                if (text == '') {
                    text = 'Системные файлы соответствуют актуальной версии'
                }
                $('#loading').html('');
                $('#iframe').html(text);
            },
            error: function (xhr) {
                $('#loading').html('');
                $('#iframe').html('<pre> Не удалось завершить сканирование. Статус: '
                    + xhr.statusCode().status + '\\nПопробуйте повторить позже.</pre>');
            },
            type: 'GET',
            dataType: "json"
        });
    }
</script>
HTML;

        return $result;
    }

    // Получаем информацию о полях из конфигурационных файлов
    protected function getFields(array $data, string $type = 'Structure'): array
    {
        $config = Config::getInstance();
        $configClass = $config->getStructureClass($data['structure'], 'Config', $type);
        /** @noinspection PhpUndefinedFieldInspection */
        $dataFields = $configClass::$fields;

        $fields = [];
        if (!isset($dataFields) || !is_array($dataFields)) {
            return $fields;
        }

        return $dataFields;
    }

    // Получаем информацию о полях из конфигурационных файлов
    protected function getFieldListWithTypes($dataFields): array
    {
        $fields = [];
        array_walk($dataFields, static function ($value, $key) use (&$fields) {
            if (isset($value['sql'])) {
                $type = '';
                // получение всех значений при указании типа "SET"
                if (strncasecmp($value['sql'], 'set', 3) === 0) {
                    preg_match('/set\(.*?\)/is', $value['sql'], $matchesType);
                    if (isset($matchesType[0])) {
                        $type = preg_replace('/\v|\s\s/i', '', $matchesType[0]);
                    }
                } else {
                    [$type] = explode(' ', $value['sql']);
                }
                if ($type) {
                    $fields[$key] = $type;
                }
            }
        });

        return $fields;
    }

    protected function diffConfigBaseType($a, $b): array
    {
        $result = [];
        foreach ($a as $k => $v) {
            if (isset($b[$k])) {
                if ($v === 'bool') {
                    $v = 'tinyint(1)';
                }
                if (!preg_match('/^' . quotemeta($v) . '(.*?)/', $b[$k])) {
                    $result[$k]['conf'] = $v;
                    $result[$k]['base'] = $b[$k];
                }
            }
        }

        return $result;
    }
}