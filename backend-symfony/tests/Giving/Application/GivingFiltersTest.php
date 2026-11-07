<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Application\GivingFilters;
use App\Giving\Domain\GivingException;
use App\Shared\Domain\CalendarDate;
use PHPUnit\Framework\TestCase;

final class GivingFiltersTest extends TestCase
{
    private const string PERIOD = 'Choose a period of at most ten years, with the end on or after the start.';

    public function testAcceptsASingleDayAndExactlyTenYearsOfDays(): void
    {
        GivingFilters::validate(self::filter('2026-01-01', '2026-01-01'));
        GivingFilters::validate(self::filter('2016-01-01', CalendarDate::parse('2016-01-01')->plusDays(3660)->iso(), 'ZAR'));
        $this->addToAssertionCount(2);
    }

    public function testRejectsMissingReversedOrTooLongPeriods(): void
    {
        $cases = [
            new GiftFilter(null, CalendarDate::parse('2026-01-01'), null, null, null, 10, 0),
            new GiftFilter(CalendarDate::parse('2026-01-01'), null, null, null, null, 10, 0),
            self::filter('2026-01-02', '2026-01-01'),
            self::filter('1899-12-31', '1900-01-31'),
            self::filter('2016-01-01', CalendarDate::parse('2016-01-01')->plusDays(3661)->iso()),
        ];
        foreach ($cases as $filter) {
            try {
                GivingFilters::validate($filter);
                self::fail('accepted '.($filter->from?->iso() ?? 'null').'..'.($filter->to?->iso() ?? 'null'));
            } catch (GivingException $e) {
                self::assertSame(self::PERIOD, $e->getMessage());
            }
        }
    }

    public function testChecksThePageBeforeThePeriod(): void
    {
        $this->expectExceptionObject(GivingException::invalid('Use a page size of 1–100 and an offset of 0–100000.'));

        GivingFilters::validate(new GiftFilter(null, null, null, null, null, 0, 0));
    }

    public function testRejectsAnUnsupportedCurrency(): void
    {
        $this->expectExceptionObject(GivingException::invalid('Choose a supported currency.'));

        GivingFilters::validate(self::filter('2026-01-01', '2026-02-01', 'JPY'));
    }

    private static function filter(string $from, string $to, ?string $currency = null): GiftFilter
    {
        return new GiftFilter(CalendarDate::parse($from), CalendarDate::parse($to), $currency, null, null, 10, 0);
    }
}
