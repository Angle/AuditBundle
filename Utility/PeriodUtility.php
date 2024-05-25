<?php

namespace Angle\AuditBundle\Utility;

use DateTime;
use DateInterval;
use DatePeriod;

use Exception;
use RuntimeException;

abstract class PeriodUtility
{
    /**
     * @param int $year
     * @param int $month
     * @return array
     */
    public static function nextMonth(int $year, int $month): array
    {
        if ($month >= 12) {
            return [$year+1, 1];
        }

        return [$year, $month+1];
    }

    /**
     * @param int $year
     * @param int $month
     * @return array
     */
    public static function previousMonth(int $year, int $month): array
    {
        if ($month <= 1) {
            return [$year-1, 12];
        }

        return [$year, $month-1];
    }

    /**
     * @param int $year
     * @param int $month
     * @return DateTime
     */
    public static function calculateStartDate(int $year, int $month): DateTime
    {
        $dateStr = sprintf('%d-%d-%d', $year, $month, 1);
        return DateTime::createFromFormat('Y-m-d', $dateStr);
    }

    /**
     * @param int $year
     * @param int $month
     * @return DateTime
     */
    public static function calculateEndDate(int $year, int $month): DateTime
    {
        // First we find the start date
        $firstDay =  self::calculateStartDate($year, $month);

        // Then we use the format key "t" to print the last day of the month, and we create a new date from that string
        return DateTime::createFromFormat('Y-m-d', $firstDay->format('Y-m-t'));
    }

    /**
     * Outputs a string in the format "yyyy-mm"
     * @param int $year
     * @param int $month
     * @return string
     */
    public static function periodStringFromYearAndMonth(int $year, int $month): string
    {
        return $year . "-" . str_pad($month, 2,'0', STR_PAD_LEFT);
    }
}