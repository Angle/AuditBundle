<?php

namespace Angle\AuditBundle\Command\Audit;

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

class DatabaseUsersCommand extends Command
{
    protected static $defaultName = 'angle:audit:database-users';

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
            ->setDescription('Audit Report: database user and grants');
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


        // 1. Display all users
        $io->writeln("1. User list with their system-wide grants:");
        try {
            $sql = <<<ENDSQL
SELECT
Host,
User,
Select_priv as `Select`,
Insert_priv as `Insert`,
Update_priv as `Update`,
Delete_priv as `Delete`,
Create_priv as `Create`,
Drop_priv as `Drop`,
Alter_priv as `Alter`,
Grant_priv as `Grant`,
Super_priv as `Super`,
plugin as `auth_plugin`,
account_locked
FROM mysql.user
ENDSQL;

            $stmt = $conn->prepare($sql);
            $users = $stmt->executeQuery()->fetchAllAssociative();

            if (count($users) == 0) {
                $io->writeln('? No results');
            } else {
                $io->table(array_keys($users[0]), $users);
            }

        } catch (\Throwable $e) {
            $io->error('Database query failed, exception raised on executeQuery():' . PHP_EOL . $e->getMessage());

            if (self::DEBUG) {
                $io->writeln('');
                $io->writeln('Trace:');
                $io->writeln($e->getTraceAsString());
            }

            $this->printGrantInstructions($io);

            ReportUtility::printEndTimestamp($io);
            $io->writeln('[Report Failure]');
            return Command::FAILURE;
        }

        $io->writeln('');



        // 2. Display per database grants
        $io->writeln("2. Special grants per-Database:");
        try {
            $sql = <<<ENDSQL
SELECT
Host,
User,
Db,
Select_priv as `Select`,
Insert_priv as `Insert`,
Update_priv as `Update`,
Delete_priv as `Delete`,
Create_priv as `Create`,
Drop_priv as `Drop`,
Alter_priv as `Alter`,
Grant_priv as `Grant`
FROM mysql.db WHERE Db = :database
ENDSQL;

            $stmt = $conn->prepare($sql);
            $stmt->bindValue('database', $conn->getDatabase());
            $rows = $stmt->executeQuery()->fetchAllAssociative();

            if (count($rows) == 0) {
                $io->writeln('✓ No results');
            } else {
                $io->table(array_keys($rows[0]), $rows);
            }

        } catch (\Throwable $e) {
            $io->error('Database query failed, exception raised on executeQuery():' . PHP_EOL . $e->getMessage());

            if (self::DEBUG) {
                $io->writeln('');
                $io->writeln('Trace:');
                $io->writeln($e->getTraceAsString());
            }

            $this->printGrantInstructions($io);

            ReportUtility::printEndTimestamp($io);
            $io->writeln('[Report Failure]');
            return Command::FAILURE;
        }
        $io->writeln('');


        // 3. Display per table grants
        $io->writeln("3. Special grants per-Table:");
        try {
            $stmt = $conn->prepare("SELECT * FROM mysql.tables_priv;");
            $rows = $stmt->executeQuery()->fetchAllAssociative();

            if (count($rows) == 0) {
                $io->writeln('✓ No results');
            } else {
                $io->table(array_keys($rows[0]), $rows);
            }
        } catch (\Throwable $e) {
            $io->error('Database query failed, exception raised on executeQuery():' . PHP_EOL . $e->getMessage());

            if (self::DEBUG) {
                $io->writeln('');
                $io->writeln('Trace:');
                $io->writeln($e->getTraceAsString());
            }

            $this->printGrantInstructions($io);

            ReportUtility::printEndTimestamp($io);
            $io->writeln('[Report Failure]');
            return Command::FAILURE;
        }
        $io->writeln('');


        // 4. Display per column grants
        $io->writeln("4. Special grants per-Column:");
        try {
            $stmt = $conn->prepare("SELECT * FROM mysql.columns_priv;");
            $rows = $stmt->executeQuery()->fetchAllAssociative();

            if (count($rows) == 0) {
                $io->writeln('✓ No results');
            } else {
                $io->table(array_keys($rows[0]), $rows);
            }
        } catch (\Throwable $e) {
            $io->error('Database query failed, exception raised on executeQuery():' . PHP_EOL . $e->getMessage());

            if (self::DEBUG) {
                $io->writeln('');
                $io->writeln('Trace:');
                $io->writeln($e->getTraceAsString());
            }

            $this->printGrantInstructions($io);

            ReportUtility::printEndTimestamp($io);
            $io->writeln('[Report Failure]');
            return Command::FAILURE;
        }
        $io->writeln('');



        /*
        // 5. Grants per User
        $io->writeln("5. Grants per User:");

        if (empty($userList)) {
            $io->writeln('✓ No results');
        }

        foreach ($userList as $userData) {
            $user = $userData['User'];
            $userHost = $userData['Host'];

            $io->writeln(sprintf(">  %s@%s individual grants:", $user, $userHost));

            try {
                $stmt = $conn->prepare("SHOW GRANTS FOR :user@:host");
                $stmt->bindValue('user', $user);
                $stmt->bindValue('host', $userHost);
                $rows = $stmt->executeQuery()->fetchAllAssociative();

                if (count($users) == 0) {
                    $io->writeln('✓ No results');
                } else {
                    //$io->table(array_keys($users[0]), $users);
                    foreach ($rows as $r) {
                        $io->writeln('   · ' . array_pop($r));
                    }
                }
            } catch (\Throwable $e) {
                $io->error('Database query failed, exception raised on executeQuery():' . PHP_EOL . $e->getMessage());

                if (self::DEBUG) {
                    $io->writeln('');
                    $io->writeln('Trace:');
                    $io->writeln($e->getTraceAsString());
                }

                $this->printGrantInstructions($io);

                ReportUtility::printEndTimestamp($io);
                $io->writeln('[Report Failure]');
                return Command::FAILURE;
            }
        } // endfor: Users
        $io->writeln('');
        */


        ReportUtility::printEndTimestamp($io);
        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }

    private function printGrantInstructions(SymfonyStyle $io): void
    {
        $io->writeln('To allow this audit report, grant the following permission to the application user:');
        $io->writeln("GRANT SELECT ON mysql.db TO '{USER}'@'%';");
        $io->writeln("GRANT SELECT ON mysql.user TO '{USER}'@'%';");
        $io->writeln("GRANT SELECT ON mysql.tables_priv TO '{USER}'@'%';");
        $io->writeln("GRANT SELECT ON mysql.columns_priv TO '{USER}'@'%';");
    }
}