<?php
declare(strict_types=1);

namespace Ideal\Command;

use Exception;
use Ideal\Core\Config;
use Ideal\Spider\Crawler;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Запускаемый скрипт модуля создания карты сайта
 *
 * Возможны несколько вариантов запуска скрипта
 *
 * 1. Из крона (с буферизацией вывода):
 * /bin/php /var/www/example.com/super/Ideal/Library/sitemap/index.php
 *
 * 2. Из командной строки из папки скрипта (без буферизации вывода):
 * /bin/php index.php
 *
 * 3. Из браузера:
 * http://example.com/super/Ideal/Library/sitemap/index.php
 *
 * 4. Принудительное создание карты сайта, даже если сегодня она и создавалась
 * /bin/php index.php w
 *
 * 5. Принудительное создание карты сайта из браузера, даже если сегодня она и создавалась
 * http://example.com/super/Ideal/Library/sitemap/index.php?w=1
 *
 */
class SiteMapCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('app:sitemap')
            ->setDescription('Запуск сбора карты сайта')
            ->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Принудительное составление xml-карты сайта')
            ->addOption(
                'clear',
                null,
                InputOption::VALUE_NONE,
                'Сброс ранее собранных страниц'
            )
            ->addOption(
                'test',
                null,
                InputOption::VALUE_NONE,
                'Тестовый запуск, без отправки сообщений почтой'
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool)$input->getOption('force');
        $clear = (bool)$input->getOption('clear');
        $test = (bool)$input->getOption('test');

        $config = Config::getInstance();
        $filePath = $config->rootDir . '/config/site_map.php';

        if (!file_exists($filePath)) {
            // todo логирование
            $output->writeln('Не найден файл конфигурации');
            return self::FAILURE;
        }

        /** @noinspection UsingInclusionReturnValueInspection */
        $params = require $filePath;

        $crawler = new Crawler($params, $force, $clear, $test);

        if (!$test) {
            ob_start();
        }

        $message = '';

        try {
            $crawler->run();
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }

        $output->writeln($message);

        if (!$test) {
            // Если было кэширование вывода, получаем вывод и отображаем его
            $text = ob_get_clean();
            $output->writeln($text);
            // Если нужно, отправляем письмо с выводом скрипта
            $crawler->sendCron($text);
        }

        // return value is important when using CI, to fail the build when the command fails
        // in case of fail: "return self::FAILURE;"
        return self::SUCCESS;
    }
}
