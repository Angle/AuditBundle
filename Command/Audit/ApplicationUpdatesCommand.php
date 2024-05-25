<?php

namespace Angle\AuditBundle\Command\Audit;

use Angle\AuditBundle\Utility\PeriodUtility;
use Angle\AuditBundle\Utility\ReportUtility;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ApplicationUpdatesCommand extends Command
{
    protected static $defaultName = 'angle:audit:application-updates';

    /** @var ParameterBagInterface $params */
    private $params;

    const DEBUG = false;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;

        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->addArgument('year', InputArgument::REQUIRED, 'Evaluation year (YYYY)')
            ->addArgument('month', InputArgument::REQUIRED, 'Evaluation month (MM)')
            ->setDescription('Audit Report: application updates applied during the period');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        ReportUtility::printStartTimestamp($io);

        $year   = intval($input->getArgument('year'));
        $month  = intval($input->getArgument('month'));
        $periodString = PeriodUtility::periodStringFromYearAndMonth($year, $month);

        $output->writeln('<info>Report Evaluation Period:</info> ' . $periodString . PHP_EOL);


        // Navigate to the project's root directory (one up from the Symfony installation folder)
        $kernelDir = $this->params->get('kernel.project_dir');
        $logFile = $kernelDir . '/../symfony-update.log';

        $output->writeln('<info>Symfony Update Log File:</info> ' . $logFile);

        if (!file_exists($logFile)) {
            $io->writeln('<error>✗ log file not found</error>' . PHP_EOL);

            ReportUtility::printEndTimestamp($io);
            $io->writeln('[Report Failure]');
            return Command::FAILURE;
        }


        $io->writeln('Reading events from symfony-update.log for the evaluation period:' . PHP_EOL);
        $atLeastOneFound = false;

        // Read file line by line
        $handle = fopen($logFile, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                /*
                 * All lines in the symfony-update.log should begin with the following prefix:
                 * echo "$(date '+%Y-%m-%d %H:%M:%S') $(whoami) - ...."
                 *
                 * Thus, we can simply filter by the first part of the string to pull the month we want
                 */

                if (str_starts_with($line, $periodString)) {
                    $output->writeln('· ' . trim($line));
                    $atLeastOneFound = true;
                }
            }

            fclose($handle);
        }

        if (!$atLeastOneFound) {
            $io->writeln('✓ No results for the evaluation period');
        }

        $io->writeln('');

        ReportUtility::printEndTimestamp($io);
        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }
}