<?php

namespace Angle\AuditBundle\Command;

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

class AuditEmailReportCommand extends Command
{
    protected static $defaultName = 'angle:audit:email-report';

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
            ->setDescription('Print an Operating System user audit report')
            ->setHelp('Print an Operating System user audit report.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database User Access Review');

        // This command will generate an Audit Report and will send it by email

        // Run each one of the Audit Commands, and generate a .txt report from them

        // The body of the email will only show the "success / failure" status of the execution of each one
        // the actual details of the report will be included in the TXT attachments from each line

        // Attach the txt reports to the email

        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }
}