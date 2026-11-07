<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DateTimeImmutable;
use DateTimeZone;

/** A date without a time of day or zone (birthdays, "as of" dates). Compared by calendar day only. */
final readonly class CalendarDate
{
    private DateTimeImmutable $day;

    private function __construct(int $year, int $month, int $day)
    {
        $this->day = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('UTC'));
    }

    public static function of(int $year, int $month, int $day): self
    {
        if (!checkdate($month, $day, $year)) {
            throw InvalidInputException::because('Invalid calendar date.');
        }

        return new self($year, $month, $day);
    }

    /** Parses an ISO-8601 calendar date (YYYY-MM-DD). */
    public static function parse(string $value): self
    {
        if (1 !== preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts)) {
            throw InvalidInputException::because('Dates must use the YYYY-MM-DD format.');
        }

        return self::of((int) $parts[1], (int) $parts[2], (int) $parts[3]);
    }

    public static function fromInstant(DateTimeImmutable $instant, DateTimeZone $zone): self
    {
        $local = $instant->setTimezone($zone);

        return new self((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'));
    }

    public function isBefore(self $other): bool
    {
        return $this->iso() < $other->iso();
    }

    public function isAfter(self $other): bool
    {
        return $this->iso() > $other->iso();
    }

    public function equals(self $other): bool
    {
        return $this->iso() === $other->iso();
    }

    public function isLeapYear(): bool
    {
        return '1' === $this->day->format('L');
    }

    public function year(): int
    {
        return (int) $this->day->format('Y');
    }

    /** Whole days from this date to $other, negative when $other is earlier (java.time ChronoUnit.DAYS.between). */
    public function daysUntil(self $other): int
    {
        return intdiv((int) $other->day->format('U') - (int) $this->day->format('U'), 86_400);
    }

    public function month(): int
    {
        return (int) $this->day->format('n');
    }

    public function dayOfMonth(): int
    {
        return (int) $this->day->format('j');
    }

    /** A leap day falls back to February 28 in a non-leap target year. */
    public function minusYears(int $years): self
    {
        $year = (int) $this->day->format('Y') - $years;
        $leap = checkdate(2, 29, $year);
        $day = 2 === $this->month() && 29 === $this->dayOfMonth() && !$leap ? 28 : $this->dayOfMonth();

        return new self($year, $this->month(), $day);
    }

    public function plusYears(int $years): self
    {
        return $this->minusYears(-$years);
    }

    /** Adds calendar months, clamping to the last day of a shorter month (31 January plus a month is 28 February). */
    public function plusMonths(int $months): self
    {
        $index = (int) $this->day->format('Y') * 12 + ($this->month() - 1) + $months;
        $year = intdiv($index, 12);
        $month = $index % 12 + 1;
        $length = (int) new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new DateTimeZone('UTC'))->format('t');

        return new self($year, $month, min($this->dayOfMonth(), $length));
    }

    public function plusDays(int $days): self
    {
        $moved = $this->day->modify(sprintf('%+d days', $days));

        return new self((int) $moved->format('Y'), (int) $moved->format('n'), (int) $moved->format('j'));
    }

    /** ISO-8601 representation, also the form stored in DATE columns. */
    public function iso(): string
    {
        return $this->day->format('Y-m-d');
    }
}
