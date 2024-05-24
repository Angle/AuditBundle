<?php

namespace Angle\AuditBundle\Utility;

use Symfony\Component\Console\Style\SymfonyStyle;

abstract class ReportUtility
{
    public static ?float $timer = null;

    public static function timestamp(): string
    {
        return (new \DateTime('now'))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function printStartTimestamp(SymfonyStyle $io): void
    {
        self::$timer = microtime(true);

        $io->writeln(sprintf('Start report execution: %s [%s @ %s]' . PHP_EOL,
            self::timestamp(),
            get_current_user(),
            gethostname()
        ));
    }

    public static function printEndTimestamp(SymfonyStyle $io): void
    {
        if (self::$timer !== null) {
            $elapsed = microtime(true) - self::$timer;
            $elapsed = number_format($elapsed, 2) . ' secs';
        } else {
            $elapsed = '?';
        }

        $io->writeln(sprintf('End report execution: %s (%s)',
            self::timestamp(),
            $elapsed
        ));

        self::$timer = null;
    }
}