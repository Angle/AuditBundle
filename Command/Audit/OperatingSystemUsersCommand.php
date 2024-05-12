<?php

namespace Angle\AuditBundle\Command\Audit;

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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        // Use the UbuntuUtility
        // Iterate through the /home folder of the server, trying to access the different users listed in there
        // and then look for the .ssh/authorized_keys files

        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }
}