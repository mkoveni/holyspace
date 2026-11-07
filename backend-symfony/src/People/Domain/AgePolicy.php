<?php

declare(strict_types=1);

namespace App\People\Domain;

use App\People\Domain\ValueObject\AgeCategory;
use App\Shared\Domain\CalendarDate;

final class AgePolicy
{
    public const int ADULT_AGE = 18;

    private function __construct()
    {
    }

    /** The latest birth date that makes someone an adult on the given day. A leap-day birthday turns 18 on February 28 in a non-leap year. */
    public static function adultBirthDateCutoff(CalendarDate $asOf): CalendarDate
    {
        $cutoff = $asOf->minusYears(self::ADULT_AGE);
        if (2 === $asOf->month() && 28 === $asOf->dayOfMonth() && !$asOf->isLeapYear() && $cutoff->isLeapYear()) {
            return $cutoff->plusDays(1);
        }

        return $cutoff;
    }

    public static function category(?CalendarDate $birth, CalendarDate $asOf): AgeCategory
    {
        if (null === $birth || $birth->isAfter($asOf)) {
            return AgeCategory::Unknown;
        }

        return $birth->isAfter(self::adultBirthDateCutoff($asOf)) ? AgeCategory::Child : AgeCategory::Adult;
    }
}
