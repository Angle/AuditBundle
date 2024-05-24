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

class DatabaseUsersCommand extends Command
{
    protected static $defaultName = 'angle:audit:database';

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
            ->addOption(
                'include-local-users',
                'a',
                InputOption::VALUE_NONE,
                'Include local users in the report'
            )
            ->setDescription('Audit Report: database user and grants');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $conn = $this->doctrine->getConnection();
        $driver = $conn->getDriver()->getDatabasePlatform()->getName();

        if ($driver != 'mysql') {
            $io->error('This audit report only works for MySQL databases.');
            return Command::FAILURE;
        }

        $output->writeln('<info>Driver:</info> ' . $driver);
        $output->writeln('<info>Database Name:</info> ' . $conn->getDatabase());

        $includeLocalUsers = $input->getOption('include-local-users');

        if ($includeLocalUsers) {
            $io->writeln('<info>Report Scope:</info> Include ALL users (%)');
        } else {
            $io->writeln('<info>Report Scope:</info> Only Remote users (exclude localhost and 127.0.0.1)');
        }

        $output->writeln('');


        $userList = [];


        // 1. Display all users
        $io->writeln("1. User list with their system-wide grants:");
        try {
            $sql = <<<ENDSQL
SELECT
    Host, User,
    Select_priv,
    Insert_priv,
    Update_priv,
    Delete_priv,
    Create_priv,
    Drop_priv,
    Reload_priv,
    Shutdown_priv,
    Process_priv,
    File_priv,
    Grant_priv,
    References_priv,
    Index_priv,
    Alter_priv,
    Show_db_priv,
    Super_priv,
    Create_tmp_table_priv,
    Lock_tables_priv,
    Execute_priv,
    Repl_slave_priv,
    Repl_client_priv,
    Create_view_priv,
    Show_view_priv,
    Create_routine_priv,
    Alter_routine_priv,
    Create_user_priv,
    Create_role_priv,
    Drop_role_priv,
    Event_priv,
    Trigger_priv,
    Create_tablespace_priv,
    plugin,
    account_locked
FROM mysql.user
-- WHERE host NOT IN ('127.0.0.1', 'localhost');
ENDSQL;

            $stmt = $conn->prepare($sql);
            $users = $stmt->executeQuery()->fetchAllAssociative();

            if (count($users) == 0) {
                $io->writeln('✓ No results');
            } else {

                foreach ($users as $u) {
                    if ((in_array($u['Host'], ['127.0.0.1', 'localhost']))) {
                        if (!$includeLocalUsers) {
                            continue;
                        }
                    }

                    $userList[] = ['Host' => $u['Host'], 'User' => $u['User']];
                }



                // First print a simple user table
                $io->writeln("> Complete User list, including local and remote users:");
                $h = ['Host', 'User', 'Auth Plugin', 'Locked'];
                $r = [];
                foreach ($users as $u) {
                    $r[] = [
                        'Host' => $u['Host'],
                        'User' => $u['User'],
                        'Auth Plugin' => $u['plugin'],
                        'Locked' => $u['account_locked'],
                    ];
                }
                $io->table($h, $r);

                if (empty($userList)) {
                    $io->writeln('✓ No detailed results after limiting report scope');
                }

                // option A: write a narrow table PER user
                $h = ['Grant', 'Value'];
                foreach ($users as $u) {
                    // Skip the user if it's not in the list
                    $found = false;
                    foreach ($userList as $ul) {
                        if ($u['User'] == $ul['User']) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) continue;

                    $io->writeln(sprintf("> %s@%s system-wide grants:", $u['User'], $u['Host']));
                    $r = [];
                    foreach ($u as $k => $v) {
                        if (in_array($k, ['Host', 'User', 'plugin', 'account_locked'], true)) continue;
                        $r[] = [$k, $v];
                    }
                    $io->table($h, $r);
                }

                /*
                // option B: write a single long table
                $io->table(array_keys($users[0]), $users);
                */
            }
        } catch (\Throwable $e) {
            $io->error('Database query failed, exception raised on executeQuery():' . PHP_EOL . 'Exception:' . PHP_EOL . $e->getMessage());

            if (self::DEBUG) {
                $io->writeln('');
                $io->writeln('Trace:');
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
        $io->writeln('');

        // Debug UserList
        //$io->table(['Host', 'User'], $userList);

        $userListFlat = '';
        foreach ($userList as $u) {
            $userListFlat .= $conn->quote($u['User']) . ',';
        }
        $userListFlat = rtrim($userListFlat, ',');

        // Debug "flat" UserList
        //$io->writeln('Flat user list: ' . $userListFlat);

        if (empty($userList)) {
            $io->writeln('! No valid users were found, ending report now.');
            $io->writeln('[End of Report]');
            return Command::SUCCESS;
        }


        // 2. Display per database grants
        $io->writeln("2. Special grants per-Database:");
        try {
            $stmt = $conn->prepare("SELECT * FROM mysql.db WHERE user IN ($userListFlat);");
            $rows = $stmt->executeQuery()->fetchAllAssociative();

            if (count($rows) == 0) {
                $io->writeln('✓ No results');
            } else {
                $io->table(array_keys($rows[0]), $rows);
            }
        } catch (\Throwable $e) {
            $io->error('Database query failed, exception raised on executeQuery():' . PHP_EOL . 'Exception:' . PHP_EOL . $e->getMessage());

            if (self::DEBUG) {
                $io->writeln('');
                $io->writeln('Trace:');
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
        $io->writeln('');


        // 3. Display per table grants
        $io->writeln("3. Special grants per-Table:");
        try {
            $stmt = $conn->prepare("SELECT * FROM mysql.tables_priv WHERE user IN ($userListFlat);");
            $rows = $stmt->executeQuery()->fetchAllAssociative();

            if (count($rows) == 0) {
                $io->writeln('✓ No results');
            } else {
                $io->table(array_keys($rows[0]), $rows);
            }
        } catch (\Throwable $e) {
            $io->error('Database query failed, exception raised on executeQuery():' . PHP_EOL . 'Exception:' . PHP_EOL . $e->getMessage());

            if (self::DEBUG) {
                $io->writeln('');
                $io->writeln('Trace:');
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
        $io->writeln('');


        // 4. Display per column grants
        $io->writeln("4. Special grants per-Column:");
        try {
            $stmt = $conn->prepare("SELECT * FROM mysql.columns_priv WHERE user IN ($userListFlat);");
            $rows = $stmt->executeQuery()->fetchAllAssociative();

            if (count($rows) == 0) {
                $io->writeln('✓ No results');
            } else {
                $io->table(array_keys($rows[0]), $rows);
            }
        } catch (\Throwable $e) {
            $io->error('Database query failed, exception raised on executeQuery():' . PHP_EOL . 'Exception:' . PHP_EOL . $e->getMessage());

            if (self::DEBUG) {
                $io->writeln('');
                $io->writeln('Trace:');
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
        $io->writeln('');



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
                $io->error('Database query failed, exception raised on executeQuery():' . PHP_EOL . 'Exception:' . PHP_EOL . $e->getMessage());

                if (self::DEBUG) {
                    $io->writeln('');
                    $io->writeln('Trace:');
                    $io->writeln($e->getTraceAsString());
                }

                return Command::FAILURE;
            }
        } // endfor: Users
        $io->writeln('');


        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }
}