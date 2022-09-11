<?php

namespace Ideal\Structure\Service\Cron;

use Ideal\Core\Config;
use Ideal\Structure\Service\SiteData\ConfigPhp;

class Action
{
    public function render(): string
    {
        $config = Config::getInstance();

        $configFile = $config->rootDir . '/config/crontab';
        $data = file_exists($configFile) ? file_get_contents($configFile) : '';

        $test = '';
        if (!file_exists($configFile) && !file_put_contents($configFile, '')) {
            $test = 'Не могу создать файл для записи заданий: ' . $configFile;
        }

        if (!is_writable($configFile)) {
            $test = 'Файл ' . $configFile . ' недоступен для записи';
        }

        if (isset($_POST['crontab'])) {
            $data = $_POST['crontab'];

            $params = [
                'site_root' => $config->rootDir,
                'domain' => '',
                'robotEmail' => '',
                'adminEmail' => '',
            ];

            $cron = new Crontab($config->rootDir . '/config/crontab', $params);
            $cronArr = $cron->parseCrontab($data);
            if (!$cron->testTasks($cronArr)) {
                $test = $cron->getMessage();
            }

            if (empty($test)) {
                file_put_contents($configFile, $_POST['crontab']);
                $cron = new Crontab($config->rootDir . '/config/crontab', $params);
                $cron->testAction();
                $test = $cron->getMessage();
            }
        }
        $result = '<div><h4>Управление задачами по расписанию</h4>';

        if ($test) {
            $result .= '<pre style="white-space: pre-line;">' . $test . '</pre>';
        }

        $result .= <<<HTML
    <form action="" method=post enctype="multipart/form-data">
        <div id="general_cron_crontab-control-group" class="form-group">
            <label class=" control-label" for="general_cron_crontab">Установленные задачи крона:</label>
            <div class=" general_cron_crontab-controls">
                <textarea class="form-control" name="crontab"
                          placeholder="Задачи не установлены. Формат: * * * * * path/to/script.php"
                          id="crontab" rows="5">$data</textarea>
                <div id="general_cron_crontab-help"></div>
            </div>
        </div>
        <input type="submit" class="btn btn-info" name="edit" value="Сохранить"/>
    </form>

    <p>&nbsp;</p>

    <p>
        Формат аналогичен системному cron'у, но указываем только название скрипта.<br>
        Если не указывать начальный слэш у выполняемого скрипта, то он будет подключаться от корня сайта.
    </p>

    <h4>Краткая справка по настройке системного крона</h4>

    <p>
        Чтобы управлять выполнением задач по расписанию из административной части необходимо в системном cron'е
        прописать запуск скрипта отвечающего за обработку этих задач.
    </p>
    <p>Для этого в терминале выполните команду:</p>
    <pre><code>/usr/bin/php cron.php test</code></pre>
    <p>
        Если тестовый запуск прошёл успешно, то можно встраивать запуск этой задачи в системный cron.
        Для этого выполните команду:
    </p>
    <pre><code>crontab -e</code></pre>
    <p>Далее в открывшемся редакторе запишите такую строку:</p>
    <pre><code>* * * * * /usr/bin/php cron.php</code></pre>
    <p>
        Эта инструкция означает запуск скрипта каждую минуту.
        Если это будет сильно нагружать сервер, то можно сделать запуск скрипта реже.
    </p>
    <p>
        Указанные здесь задачи будут выполнены в момент запуска скрипта обработчика задач,
        даже если их время прошло (если они ещё не запускались).
    </p>
</div>
HTML;
        return $result;
    }
}
