<?php

namespace Angle\AuditBundle\Command\Audit;

use Angle\AuditBundle\Utility\ReportUtility;
use Angle\AuditBundle\Utility\UbuntuUtility;
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

class OperatingSystemUsersCommand extends Command
{
    protected static $defaultName = 'angle:audit:operating-system-users';

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
            ->setDescription('Audit Report: operating system user list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        ReportUtility::printStartTimestamp($io);

        if (!UbuntuUtility::isSupportedUbuntu()) {
            $io->writeln('<error>✗ Unsupported Operating System</error>' . PHP_EOL);

            ReportUtility::printEndTimestamp($io);
            $io->writeln('[Report Failure]');
            return Command::FAILURE;
        }


        $io->writeln('Looking for SSH AuthorizedKey files in the server...' . PHP_EOL);

        $authorizedKeys = UbuntuUtility::getSSHAuthorizedKeys();

        foreach ($authorizedKeys as $user => $keys) {
            $io->writeln('<info>OS User: ' . $user . '</info>');

            foreach ($keys as $key) {
                $io->writeln('· ' . trim($key));
            }
        }

        ReportUtility::printEndTimestamp($io);
        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }
}