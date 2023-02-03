<?php
namespace Ideal\Structure\Service\Cron;

use Cron\CronExpression;
use DateTime;
use Exception;
use samejack\PHP\ArgvParser;

class Crontab
{
    /** @var array Обработанный список задач крона */
    protected array $cron = [];

    /** @var string Путь к файлу с задачами крона */
    protected string $cronFile;

    /** @var string Адрес, на который будут высылаться уведомления с крона */
    protected string $cronEmail;

    /** @var DateTime Время последнего успешного запуска крона */
    protected DateTime $modifyTime;

    /** @var array Настройки запуска крона */
    protected array $data;

    /** @var string Корень сайта */
    protected string $siteRoot;

    /** @var string Сообщение после тестового запуска скрипта */
    protected string $message = '';

    /**
     * CronClass constructor.
     *
     * @param string $cronFile Путь к файлу crontab сайта
     * @param array $params Массив параметров сайта
     */
    public function __construct(string $cronFile, array $params)
    {
        $this->cronFile = $cronFile;
        // Корень сайта, относительно которого указываются скрипты в кроне
        $this->siteRoot = $params['site_root'];
        $this->data = [
            'domain' => $params['domain'],
            'robotEmail' => $params['robotEmail'],
            'adminEmail' => $params['adminEmail'],
        ];
    }

    /**
     * Делает проверку на доступность файла настроек, правильность заданий в системе и возможность
     * модификации скрипта обработчика крона
     */
    public function testAction(): bool
    {
        $success = true;

        // Проверяем доступность запускаемого файла для изменения его даты
        if (is_writable($this->cronFile)) {
            $this->message .= 'Файл "' . $this->cronFile . '" позволяет вносить изменения в дату модификации' . "\n";
        } else {
            $this->message .= 'Не получается изменить дату модификации файла "' . $this->cronFile . '"' . "\n";
            $success = false;
        }

        // Загружаем данные из cron-файла в переменные
        try {
            $this->loadCrontab($this->cronFile);
        } catch (Exception $e) {
            $this->message .= $e->getMessage() . "\n";
            $success = false;
        }

        // Проверяем правильность задач в файле
        if (!$this->testTasks($this->cron)) {
            $success = false;
        }

        return $success;
    }

    /**
     * Проверяет правильность введённых задач
     *
     * @param array $cron Список задач крона
     * @return bool Правильно или нет записаны задачи крона
     */
    public function testTasks(array $cron): bool
    {
        $success = true;
        $taskIsset = false;
        $currentTasks = '';
        $tasks = '';
        foreach ($cron as $cronTask) {
            [$taskExpression, $fileTask] = $this->parseCronTask($cronTask);

            // Проверяем правильность написания выражения для крона и существование файла для выполнения
            if (CronExpression::isValidExpression($taskExpression) !== true) {
                $this->message .= "Неверное выражение \"$taskExpression\"\n";
                $success = false;
            }

            // todo проверка наличия исполняемого файла на диске
//            if ($fileTask && !is_readable($fileTask)) {
//                $this->message .= "Файл \"{$fileTask}\" не существует или он недоступен для чтения\n";
//                $success = false;
//            } elseif (!$fileTask) {
//                $this->message .= "Не задан исполняемый файл для выражения \"{$taskExpression}\"\n";
//                $success = false;
//            }

            // Получаем дату следующего запуска задачи
            $cronModel = CronExpression::factory($taskExpression);
            $nextRunDate = $cronModel->getNextRunDate(new DateTime());

            $tasks .= $cronTask . "\nСледующий запуск файла \"$fileTask\" назначен на "
                . $nextRunDate->format('d.m.Y H:i:s') . "\n";
            $taskIsset = true;

            // Если дата следующего запуска меньше, либо равна текущей дате, то добавляем задачу на запуск
            $now = new DateTime();
            if ($nextRunDate <= $now) {
                $currentTasks .= $cronTask . "\n" . $fileTask . "\n"
                    . 'modify: ' . $this->modifyTime->format('d.m.Y H:i:s') . "\n"
                    . 'nextRun: ' . $nextRunDate->format('d.m.Y H:i:s') . "\n"
                    . 'now: ' . $now->format('d.m.Y H:i:s') . "\n";
            }
        }

        // Если в задачах из настроек Ideal CMS не обнаружено ошибок, уведомляем об этом
        if ($success && $taskIsset) {
            $this->message .= "В задачах из файла crontab ошибок не обнаружено\n";
        } elseif (!$taskIsset) {
            $this->message .= implode("\n", $cron) . "Пока нет ни одного задания для выполнения\n";
        }

        // Отображение информации о задачах, требующих запуска в данный момент
        if ($currentTasks && $success) {
            $this->message .= "\nЗадачи для запуска в данный момент:\n";
            $this->message .= $currentTasks;
        } elseif ($taskIsset && $success) {
            $this->message .= "\nВ данный момент запуск задач не требуется\n";
        }

        // Отображение информации о запланированных задачах и времени их запуска
        $tasks = $tasks && $success ? "\nЗапланированные задачи:\n" . $tasks : '';
        $this->message .= $tasks . "\n";

        return $success;
    }

    /**
     * Обработка задач крона и запуск нужных задач
     * @throws Exception
     */
    public function runAction(): void
    {
        // Загружаем данные из cron-файла
        $this->loadCrontab($this->cronFile);

        // Переопределяем стандартный обработчик ошибок для отправки уведомлений на почту
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {

            // Формируем текст ошибки
            $_err = 'Ошибка [' . $errno . '] ' . $errstr . ', в строке ' . $errline . ' файла ' . $errfile;

            // Выводим текст ошибки
            echo $_err . "\n";

            // Формируем заголовки письма и отправляем уведомление об ошибке на почту ответственного лица
            $header = "From: \"{$this->data['domain']}\" <{$this->data['robotEmail']}>\r\n";
            $header .= 'Content-type: text/plain; charset="utf-8"';
            mail($this->data['adminEmail'], 'Ошибка при выполнении крона', $_err, $header);
        });


        // Обрабатываем задачи для крона из настроек Ideal CMS
        $nowCron = new DateTime();
        foreach ($this->cron as $cronTask) {
            [$taskExpression, $fileTask] = $this->parseCronTask($cronTask);
            if (!$taskExpression || !$fileTask) {
                continue;
            }
            // Получаем дату следующего запуска задачи
            $cron = CronExpression::factory($taskExpression);
            $nextRunDate = $cron->getNextRunDate($this->modifyTime);
            $nowCron = new DateTime();

            // Если дата следующего запуска меньше, либо равна текущей дате, то запускаем скрипт
            if ($nextRunDate <= $nowCron) {
                // Что бы не случилось со скриптом, изменяем дату модификации файла содержащего задачи для крона
                touch($this->cronFile, $nowCron->getTimestamp());
                // todo завести отдельный временный файл, куда записывать дату последнего прохода по крону, чтобы
                // избежать ситуации, когда два задания должны выполниться в одну минуту, а выполняется только одно

                // Запускаем скрипт
                if (mb_strpos($fileTask, 'bin/console') !== false) {
                    // Это консольная команда, парсим вводные данные
                    $argvParser = new ArgvParser();
                    $params = $argvParser->parseConfigs($fileTask);
                    $keys = array_keys($params);
                    $params = array_merge(['command' => $keys[1]], array_slice($params, 2));
                    require $this->siteRoot . '/bin/console';
                } else {
                    // Это просто скрипт, запускаем его с помощью подключения
                    require_once $fileTask;
                }

                break; // Прекращаем цикл выполнения задач, чтобы не произошло наложения задач друг на друга
            }
        }

        // Изменяем дату модификации файла содержащего задачи для крона
        touch($this->cronFile, $nowCron->getTimestamp());
    }

    /**
     * Возвращает сообщения после тестирования cron'а
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Разбирает задачу для крона из настроек Ideal CMS
     * @param string $cronTask Строка в формате "* * * * * /path/to/file.php"
     * @return array Массив, где первым элементом является строка соответствующая интервалу запуска задачи по крону,
     *               а вторым элементом является путь до запускаемого файла
     */
    protected function parseCronTask(string $cronTask): array
    {
        // Получаем cron-формат запуска файла в первых пяти элементах массива и путь до файла в последнем элементе
        $taskParts = explode(' ', $cronTask, 6);
        $fileTask = '';
        if (count($taskParts) >= 6) {
            $fileTask = array_pop($taskParts);
        }

        // Если запускаемый скрипт указан относительно корня сайта, то абсолютизируем его
        if ($fileTask && strncmp($fileTask, '/', 1) !== 0) {
            $fileTask = $this->siteRoot . '/' . $fileTask;
        }

        $fileTask = trim($fileTask);

        $taskExpression = implode(' ', $taskParts);
        return [$taskExpression, $fileTask];
    }

    /**
     * Загружаем данные из крона в переменные cron, cronEmail, modifyTime
     *
     * @throws Exception
     */
    private function loadCrontab($fileName): void
    {
        $fileName = stream_resolve_include_path($fileName);
        if ($fileName) {
            $this->cron = $this->parseCrontab(file_get_contents($fileName));
        } else {
            $this->cron = [];
            $fileName = stream_resolve_include_path(dirname($fileName)) . 'crontab';
            file_put_contents($fileName, '');
        }
        $this->cronFile = $fileName;

        // Получаем дату модификации скрипта (она же считается датой последнего запуска)
        $this->modifyTime = new DateTime();
        $this->modifyTime->setTimestamp(filemtime($fileName));
    }

    /**
     * Извлечение почтового адреса для отправки уведомлений с крона. Формат MAILTO="email@email.com"
     *
     * @param string $cronString Необработанный crontab
     * @return array Обработанный crontab
     */
    public function parseCrontab(string $cronString): array
    {
        $cron = explode(PHP_EOL, $cronString);
        foreach ($cron as $k => $item) {
            $item = trim($item);
            if (empty($item)) {
                // Пропускаем пустые строки
                continue;
            }
            if ($item[0] === '#') {
                // Если это комментарий, то удаляем его из задач
                unset($cron[$k]);
                continue;
            }
            if (stripos($item, 'mailto') !== false) {
                // Если это адрес, извлекаем его
                $arr = explode('=', $item);
                if (empty($arr[1])) {
                    $this->message = 'Некорректно указан почтовый ящик для отправки сообщений';
                }
                $email = trim($arr[1]);
                $this->cronEmail = trim($email, '"\'');
                // Убираем строку с адресом из списка задач
                unset($cron[$k]);
            }
        }
        return $cron;
    }
}
