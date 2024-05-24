<?php

namespace Angle\AuditBundle\Command\Audit;

use Angle\AuditBundle\Utility\ReportUtility;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;

class DatabaseMigrationsCommand extends Command
{
    protected static $defaultName = 'angle:audit:database-migrations';

    /** @var ManagerRegistry $doctrine */
    private $doctrine;
    /** @var EntityManagerInterface $em */
    private $em;

    /** @var ParameterBagInterface $params */
    private $params;
    /** @var TableMetadataStorageConfiguration $storageConfiguration */
    private $storageConfiguration;

    const DEBUG = false;

    public function __construct(ManagerRegistry $doctrine, EntityManagerInterface $em, ParameterBagInterface $params, TableMetadataStorageConfiguration $storageConfiguration)
    {
        $this->doctrine = $doctrine;
        $this->em = $em;

        $this->params = $params;
        $this->storageConfiguration = $storageConfiguration;

        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setDescription('Audit Report: database migrations executed during the period');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        ReportUtility::printStartTimestamp($io);

        $conn = $this->doctrine->getConnection();
        $driver = $conn->getDriver()->getDatabasePlatform()->getName();

        if ($driver != 'mysql') {
            $io->error('This audit report only works for MySQL databases.');
            return Command::FAILURE;
        }

        $output->writeln('<info>Driver:</info> ' . $driver);
        $output->writeln('<info>Database Name:</info> ' . $conn->getDatabase());
        $output->writeln('<info>Doctrine Migrations Storage Table Name:</info> ' . $this->storageConfiguration->getTableName());
        $output->writeln('');

        // Executing the built-in method
        $cmd = 'doctrine:migrations:list';
        $command = $this->getApplication()->find($cmd);

        $output->writeln(sprintf('Executing built-in command %s (%s)' . PHP_EOL, $command->getName(), $command->getDescription()));

        $cmdInput = new ArrayInput([]); // no parameters required
        $cmdOutput = new BufferedOutput();

        $returnCode = $command->run($cmdInput, $cmdOutput);
        $content = $cmdOutput->fetch();

        // Display the output
        $output->writeln($content);


        ReportUtility::printEndTimestamp($io);
        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }
}