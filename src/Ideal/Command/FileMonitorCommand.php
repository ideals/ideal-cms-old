<?php
declare(strict_types=1);

namespace Ideal\Command;

use Exception;
use Ideal\Core\Config;
use Ideal\FileMonitor\FileMonitor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FileMonitorCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('app:file-monitor')
             ->setDescription('Мониторинг файлов сайта на предмет изменений')
             ->addArgument('period', InputArgument::OPTIONAL, 'Период проверки (daily, hourly)')
             ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Принудительный запуск мониторинга')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool)$input->getOption('force');
        $period = $input->getArgument('period') ?: 'daily';

        $config = Config::getInstance();

        $settings = [
            'scanDir' => $config->monitoring['scanDir'] === '' ? $config->rootDir : $config->monitoring['scanDir'],
            'tmpDir' => $config->rootDir . '/' . $config->cms['tmpFolder'],
            'scriptTime' => 50,
            'from' => $config->robotEmail,
            'to' => $config->cms['adminEmail'],
            'isFromParameter' => (bool)$config->smtp['isFromParameter'],
            'domain' => $config->domain,
            'exclude' => $config->monitoring['exclude'],
            'period' => $period,
        ];

        // Запускаем мониторинг файлов
        $files = new FileMonitor($settings, $force);
        $files->scan();

        return self::SUCCESS;
    }
}
