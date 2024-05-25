<?php

namespace Angle\AuditBundle\Command\Audit;

use Angle\AuditBundle\Utility\PeriodUtility;
use Angle\AuditBundle\Utility\ReportUtility;
use DateTime;
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

class OperatingSystemAccessCommand extends Command
{
    protected static $defaultName = 'angle:audit:operating-system-access';

    const DEBUG = false;

    public function __construct()
    {
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
            ->setDescription('Audit Report: operating system access log review');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        ReportUtility::printStartTimestamp($io);

        $year   = intval($input->getArgument('year'));
        $month  = intval($input->getArgument('month'));
        $periodString = PeriodUtility::periodStringFromYearAndMonth($year, $month);

        list($nextYear, $nextMonth) = PeriodUtility::nextMonth($year, $month);

        $firstDayOfEvaluationMonth  = PeriodUtility::calculateStartDate($year, $month);
        $firstDayOfNextMonth        = PeriodUtility::calculateStartDate($nextYear, $nextMonth);

        $output->writeln('<info>Report Evaluation Period:</info> ' . PeriodUtility::periodStringFromYearAndMonth($year, $month) . PHP_EOL);


        // 1. Display the sshd.log file
        $io->writeln(">> 1. sshd connections in the evaluation period from sshd.log");
        $io->writeln("* Note: due to log rotation configuration in the server, this journal might not contain the complete evaluation period." . PHP_EOL);

        $sshdLogFile = '/var/log/sshd.log';


        if (!file_exists($sshdLogFile)) {
            $io->writeln('<error>✗ log file not found</error>' . PHP_EOL);
        } else {
            $atLeastOneFound = false;
            // Read file line by line
            $handle = fopen($sshdLogFile, "r");
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    // sample date in the sshd.log
                    // 2024-05-25T01:42:53.571228+00:00 hostname sshd[1094324]: User child is on pid 1094379

                    // we will not try to parse the timestamp.. instead, we will simply compare the first few
                    // characters of the string ;)

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
        }

        $io->writeln('');



        // 2. Attempt to use journalctl
        $io->writeln(">> 2. sshd journalctl in the evaluation period");
        $io->writeln("* Note: due to log rotation configuration in the server, this journal might not contain the complete evaluation period." . PHP_EOL);

        // journalctl -u ssh --since "2024-05-01" --until "2024-06-01"
        $cmd = sprintf('journalctl -u ssh --since "%s" --until "%s"', $firstDayOfEvaluationMonth->format('Y-m-d'), $firstDayOfNextMonth->format('Y-m-d'));

        $cmdOutput = trim(@shell_exec($cmd));
        $io->writeln($cmdOutput);

        $io->writeln('');



        // 3. execute "lastlog", this will pull all the users in the system
        $io->writeln(">> 3. Run lastlog to verify all users in the system" . PHP_EOL);

        $cmdOutput = trim(@shell_exec('lastlog'));
        $io->writeln($cmdOutput);

        $io->writeln('');



        ReportUtility::printEndTimestamp($io);
        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }
}