<?php

namespace Angle\AuditBundle\Command;

use Angle\AuditBundle\Utility\PeriodUtility;
use Angle\AuditBundle\Utility\ReportUtility;
use Angle\Utilities\SlugUtility;
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

use Swift_Mailer as Swift_Mailer;
use Swift_Message as Swift_Message;
use Swift_Attachment as Swift_Attachment;
use Swift_Events_EventListener;
use Swift_Mime_ContentEncoder_PlainContentEncoder as Swift_Mime_ContentEncoder_PlainContentEncoder;

// Commands to be executed
use Angle\AuditBundle\Command\Audit\ApplicationUpdatesCommand;
use Angle\AuditBundle\Command\Audit\DatabaseMigrationsCommand;
use Angle\AuditBundle\Command\Audit\DatabaseUsersCommand;
use Angle\AuditBundle\Command\Audit\OperatingSystemAccessCommand;
use Angle\AuditBundle\Command\Audit\OperatingSystemUsersCommand;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AuditEmailReportCommand extends Command
{
    protected static $defaultName = 'angle:audit:report-email';

    /** @var Swift_Mailer $mailer */
    protected $mailer;

    /** @var ParameterBagInterface $params */
    private $params;

    private ?string $mailerFrom;

    public function __construct(Swift_Mailer $mailer, ParameterBagInterface $params, string $mailerFrom)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->params = $params;
        $this->mailerFrom = $mailerFrom;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->addArgument('applicationName', InputArgument::REQUIRED, 'Application Name (title)')
            ->addArgument('recipients', InputArgument::REQUIRED, 'Recipients (comma separated list, no spaces)')
            ->addArgument('year', InputArgument::OPTIONAL, 'Evaluation year (YYYY)')
            ->addArgument('month', InputArgument::OPTIONAL, 'Evaluation month (MM)')
            ->setDescription('Execute all audit reports and send the results by email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting output buffering... all content will be caught and flushed later in the execution, please be patient while the command runs...');

        // CATCH BUFFER! This will be used to later send the same details via email
        ob_start();

        $io = new SymfonyStyle($input, $output);
        $io->title('Master Audit Process - run all reports and send the results by email');
        ReportUtility::printStartTimestamp($io);

        $applicationName = $input->getArgument('applicationName');
        $applicationEnvironment = $this->params->get('kernel.environment');
        $applicationNameSlug = SlugUtility::slugify($applicationName);

        $recipientsRaw = $input->getArgument('recipients');
        $recipients = explode(',', $recipientsRaw);

        // validate all emails
        foreach ($recipients as $r) {
            if (!filter_var($r, FILTER_VALIDATE_EMAIL)) {
                $io->writeln('<error>ERROR</error> Invalid email address(es) for recipient(s)');

                ReportUtility::printEndTimestamp($io);
                $io->writeln('[Report Failure]');
                return Command::FAILURE;
            }
        }

        // Attempt to infer the execution date
        $year   = intval($input->getArgument('year'));
        $month  = intval($input->getArgument('month'));

        if (!$year || !$month) {
            // a valid period was NOT provided, we will use the previous month from today
            $today = new \DateTime('now');
            list($year, $month) = PeriodUtility::previousMonth($today->format('Y'), $today->format('n'));
        }

        $periodString = PeriodUtility::periodStringFromYearAndMonth($year, $month);


        $output->writeln(sprintf('<info>Application:</info> %s [%s]', $applicationName, $applicationEnvironment));
        $output->writeln('<info>Report Evaluation Period:</info> ' . $periodString);
        $output->writeln('<info>Recipients:</info>');
        foreach ($recipients as $r) {
            $output->writeln('· ' . $r);
        }

        $output->writeln('');


        // Run each one of the Audit Commands, and generate a .txt report from them
        $output->writeln('== AUDIT REPORTS ==');

        $commands = [
            [
                'name' => ApplicationUpdatesCommand::getDefaultName(),
                'arguments' => [
                    'command'           => ApplicationUpdatesCommand::getDefaultName(),
                    'year'              => $year,
                    'month'             => $month,
                ],
                'attachment' => 'application-updates', // attachment filename in the email
            ],
            [
                'name' => DatabaseMigrationsCommand::getDefaultName(),
                'arguments' => [
                    'command'           => DatabaseMigrationsCommand::getDefaultName(),
                    'year'              => $year,
                    'month'             => $month,
                ],
                'attachment' => 'database-migrations', // attachment filename in the email
            ],
            [
                'name' => DatabaseUsersCommand::getDefaultName(),
                'arguments' => [
                    'command'           => DatabaseUsersCommand::getDefaultName(),
                ],
                'attachment' => 'database-users', // attachment filename in the email
            ],
            [
                'name' => OperatingSystemAccessCommand::getDefaultName(),
                'arguments' => [
                    'command'           => OperatingSystemAccessCommand::getDefaultName(),
                    'year'              => $year,
                    'month'             => $month,
                ],
                'attachment' => 'operating-system-access', // attachment filename in the email
            ],
            [
                'name' => OperatingSystemUsersCommand::getDefaultName(),
                'arguments' => [
                    'command'           => OperatingSystemUsersCommand::getDefaultName(),
                ],
                'attachment' => 'operating-system-users', // attachment filename in the email
            ],
        ];

        $i = 0;
        $n = count($commands);
        $attachments = [];
        foreach ($commands as $cmd) {
            $i++;

            $command = $this->getApplication()->find($cmd['name']);
            // $command->getName(); // the string angle:audit:blah-blah-blah
            // $command->getDescription(); // the description string configured within each command

            $io->writeln(sprintf('[%d/%d] %s', $i, $n, $command->getName()));
            $io->writeln($command->getDescription());

            $cmdInput = new ArrayInput($cmd['arguments']);
            $cmdOutput = new BufferedOutput();

            // Execute the command
            try {
                $returnCode = $command->run($cmdInput, $cmdOutput);
            } catch (\Throwable $e) {
                $io->writeln('<error>! ERROR</error> ' . $e->getMessage() . PHP_EOL);
                continue;
            }

            // $content includes the actual command execution output (text)
            $content = $cmdOutput->fetch();

            if ($returnCode !== Command::SUCCESS) {
                // The command failed.
                $io->write('<error>✗ FAIL</error> ');
            } else {
                // The command succeeded
                $io->write('<info>✓ SUCCESS</info> ');
            }


            $tempFilename = $applicationNameSlug . '_' . $cmd['attachment'] . '_' . $periodString . '.txt';
            $io->writeln($tempFilename);

            // Store the contents in a tempfile
            $tempFile = tmpfile();
            fwrite($tempFile, $content);
            rewind($tempFile);

            $attachments[] = [
                'resource' => $tempFile,
                'filename' => $tempFilename,
            ];

            $io->writeln('');
        }

        ReportUtility::printEndTimestamp($io);
        $io->writeln('[End of Report]');

        // Extract the buffer
        $reportBody = ob_get_contents();
        ob_end_flush();

        $io->writeln('');
        $io->writeln('Output buffer has been collected!');

        ## PREPARE EMAIL OUTPUT
        // The body of the email will only show the "success / failure" status of the execution of each one
        // the actual details of the report will be included in the TXT attachments from each line
        $io->writeln('Preparing email output...');

        # Message metadata
        $mailFrom = [$this->mailerFrom => $applicationName . '(System Audit)'];
        $mailTitle = sprintf('System Audit Report for %s (%s)', $applicationName, $periodString);

        ## Build message object
        $plainEncoder = new Swift_Mime_ContentEncoder_PlainContentEncoder('8bit', true);

        $message = (new Swift_Message($mailTitle))
            ->setEncoder($plainEncoder) // Disable Quoted-Printable headers (they mess up HTML)
            ->setSubject($mailTitle)
            ->setFrom($mailFrom)
            ->setBcc($recipients)
            ->setBody($reportBody, 'text/plain')
            //->addPart($htmlBody, 'text/html')
        ;

        // add all attachments to the message
        foreach ($attachments as $a) {
            $attachment = new Swift_Attachment(stream_get_contents($a['resource']), $a['filename'], 'text/plain');
            $message->attach($attachment);
        }

        try {
            $this->mailer->send($message);
        // } catch (\Swift_TransportException $e) {
        } catch (\Throwable $e) {
            $io->error('Failed to send report email.');
            $io->writeln($e->getMessage());

            ReportUtility::printEndTimestamp($io);
            $io->writeln('[Report Failure]');
            return Command::FAILURE;
        }

        // force a transport restart
        $this->mailer->getTransport()->stop();


        // Close all tempfiles, this will remove them from the filesystem too
        foreach ($attachments as $a) {
            @fclose($a['resource']);
        }


        ReportUtility::printEndTimestamp($io);
        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }
}