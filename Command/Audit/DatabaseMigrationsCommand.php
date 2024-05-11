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

class DatabaseMigrationsCommand extends Command
{
    protected static $defaultName = 'angle:audit:database-migrations';

    /** @var ManagerRegistry $doctrine */
    private $doctrine;
    /** @var EntityManagerInterface $em */
    private $em;

    const DEBUG = false;

    public function __construct(ManagerRegistry $doctrine, EntityManagerInterface $em)
    {
        $this->doctrine = $doctrine;
        $this->em = $em;

        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setDescription('Print a Database Migrations user audit report')
            ->setHelp('Print a Database Migrations user audit report.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Migrations Review');

        $conn = $this->doctrine->getConnection();
        $driver = $conn->getDriver()->getDatabasePlatform()->getName();

        if ($driver != 'mysql') {
            $io->error('This audit report only works for MySQL databases.');
            return Command::FAILURE;
        }

        $output->writeln('<info>Driver:</info> ' . $driver);
        $output->writeln('<info>Database Name:</info> ' . $conn->getDatabase());


        // TODO: Implement Audit Report

        // Check if the correct Doctrine Migrations table exists
        // TODO: can we pull the parameter from the Doctrine configuration??
        // IF not, we will default to Angle's preferred table name
        // and we will print out that table, basically.


        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }
}