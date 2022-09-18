<?php
declare(strict_types=1);

namespace Ideal\Command;

use Exception;
use Ideal\Core\Config;
use Ideal\Structure\Service\Cron\Crontab;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CronCommand extends Command
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('app:cron')
             ->setDescription('Запуск крон-заданий, определённых в админке')
             ->addOption(
                 'test',
                 null,
                 InputOption::VALUE_NONE,
                 'Тестовый запуск, без выполнения заданий'
             );

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
        $test = (bool)$input->getOption('test');

        $config = Config::getInstance();

        $params = [
            'site_root' => $config->rootDir,
            'domain' => $config->domain,
            'robotEmail' => $config->robotEmail,
            'adminEmail' => $config->cms['adminEmail'],
        ];

        $cron = new Crontab($config->rootDir . '/config/crontab', $params);

        if ($test) {
            $cron->testAction();
            echo $cron->getMessage();
        } else {
            $cron->runAction();
        }

        // return value is important when using CI, to fail the build when the command fails
        // in case of fail: "return self::FAILURE;"
        return self::SUCCESS;
    }
}
