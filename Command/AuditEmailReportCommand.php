<?php

namespace Angle\AuditBundle\Command;

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

    public function __construct(Swift_Mailer $mailer, ParameterBagInterface $params)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->params = $params;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setDescription('Execute all audit reports and send the results by email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Master Audit Process - run all reports and send the results by email');

        $year = 2024;
        $period = 4;
        $recipientsRaw = 'efuentes@angle.mx,ahernandez@angle.mx';
        $recipients = explode(',', $recipientsRaw);

        // TODO: Get hostname (os)
        // TODO: get OS username (whoami)
        get_current_user();
        gethostname();
        // TODO: get symfony environment
        $this->params->get('kernel.environment');

        // TODO: if a "host-title" "title" (something like that) option is passed to the command, we will use that in the Email instead of the hostname.
        // The hostname in AWS usually is something like "ip-10-230-10-4" which is not very useful

        // TODO: read MAILFER_FROM from the environment variable

        // TODO: read SwiftMailer configuration

        // validate all emails
        foreach ($recipients as $r) {
            if (!filter_var($r, FILTER_VALIDATE_EMAIL)) {
                $io->writeln('<error>ERROR</error> Invalid email address(es) for recipient(s)');
                return Command::FAILURE;
            }
        }

        // This command will generate an Audit Report and will send it by email

        $commands = [
            [
                'name' => ApplicationUpdatesCommand::getDefaultName(),
                'arguments' => [
                    'command'           => ApplicationUpdatesCommand::getDefaultName(),
                    'year'              => $year,
                    'period'            => $period,
                ],
            ],
            [
                'name' => DatabaseMigrationsCommand::getDefaultName(),
                'arguments' => [
                    'command'           => DatabaseMigrationsCommand::getDefaultName(),
                    'year'              => $year,
                    'period'            => $period,
                ],
            ],
            [
                'name' => DatabaseUsersCommand::getDefaultName(),
                'arguments' => [
                    'command'           => DatabaseUsersCommand::getDefaultName(),
                ],
            ],
            [
                'name' => OperatingSystemAccessCommand::getDefaultName(),
                'arguments' => [
                    'command'           => OperatingSystemAccessCommand::getDefaultName(),
                    'year'              => $year,
                    'period'            => $period,
                ],
            ],
            [
                'name' => OperatingSystemUsersCommand::getDefaultName(),
                'arguments' => [
                    'command'           => OperatingSystemUsersCommand::getDefaultName(),
                ],
            ],
        ];


        // Run each one of the Audit Commands, and generate a .txt report from them
        $i = 0;
        $n = count($commands);
        foreach ($commands as $cmd) {
            $i++;

            $command = $this->getApplication()->find($cmd['name']);
            $command->getName(); // the string angle:audit:blah-blah-blah
            $command->getDescription(); // the description string configured within each command

            $cmdInput = new ArrayInput($cmd['arguments']);

            $cmdOutput = new BufferedOutput();

            // 4. Execute the command
            if ($v) $output->write(sprintf('> running %d/%d .. ', $i, $n));
            $returnCode = $command->run($cmdInput, $cmdOutput);
            $content = $cmdOutput->fetch();

            // $content includes the actual command execution output (text)

            if ($returnCode !== Command::SUCCESS) {
                // The command failed.
                // TODO: Do something
                $output->writeln('<error>FAIL</error>');
                break;
            } else {
                // The command succeeded
                // TODO: Do something
                $output->writeln('<info>OK</info>');
            }
        }

        ## PRINT OUT THE SUMMARIZED RESULT IN THE CONSOLE
        // TODO: we might be able to print this in the loop above, should be cleaner


        ## PREPARE EMAIL OUTPUT
        // The body of the email will only show the "success / failure" status of the execution of each one
        // the actual details of the report will be included in the TXT attachments from each line

        // TODO: include additional system/environment information, such as application name, hostname, symfony environment, etc.

        # Message metadata
        $mailFrom = [$mailerFrom => 'System Audit'];
        $mailTitle = sprintf('System Audit Report for %s (%04d-%02d)', $systemName, $year, $period);

        ## Build message object
        $plainEncoder = new Swift_Mime_ContentEncoder_PlainContentEncoder('8bit', true);

        $message = (new Swift_Message($mailTitle))
            ->setEncoder($plainEncoder) // Disable Quoted-Printable headers (they mess up HTML)
            ->setSubject($mailTitle)
            ->setFrom($mailFrom)
            ->setBcc($recipients)
            ->setBody($mailBody, 'text/html')
            ->addPart($plainBody, 'text/plain')
        ;

        // TODO: Add all report outputs as attachments
        foreach ($reports as $r) {
            try {
                $pdf = $this->aws->s3GetObject($broadcast->getAttachment()->getS3Key());

                // Build a temporary name for the email
                $downloadName = sprintf('attachment-%s.%s', $broadcast->getCode(), $broadcast->getAttachment()->getExtension());

                $attachment = new Swift_Attachment($pdf['Body'], $downloadName, $pdf['ContentType']);

                $message->attach($attachment);
            }  catch (\Throwable $e) {
                // could not download file from S3, we'll assume it does not exist, oh well, we'll just skip ti
            }
        }

        try {
            $this->mailer->send($message);
        } catch (\Swift_TransportException $e) {
            // TODO: implement proper debugging..
            //echo $e->getMessage();
            $io->error('Failed to send report email');
            $io->writeln($e->getMessage());
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error('Failed to send report email');
            $io->writeln($e->getMessage());
            return Command::FAILURE;
        }

        // force a transport restart
        $this->mailer->getTransport()->stop();


        $io->writeln('[End of Report]');

        return Command::SUCCESS;
    }
}