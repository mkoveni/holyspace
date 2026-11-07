<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Domain;

use App\Volunteering\Domain\Availability;
use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AvailabilityTest extends TestCase
{
    public function testUnknownIsNotAvailableAndAdjacentWindowsMergeButUnavailableExceptionsWin(): void
    {
        $from = self::at('2026-10-25T08:00:00Z');
        $until = $from->modify('+2 hours');
        self::assertFalse(Availability::covers($from, $until, [], []));

        $first = new AvailabilityException($from, $from->modify('+1 hour'), true);
        $second = new AvailabilityException($from->modify('+1 hour'), $until, true);
        self::assertTrue(Availability::covers($from, $until, [], [$first, $second]));

        $blocked = new AvailabilityException($from->modify('+30 seconds'), $from->modify('+40 seconds'), false);
        self::assertFalse(Availability::covers($from, $until, [], [$first, $second, $blocked]));
    }

    public function testOvernightRulesUseTheRuleTimeZoneAndHalfOpenEffectiveDates(): void
    {
        $rule = new AvailabilityRule(7, '22:00:00', '02:00:00', 'Africa/Johannesburg', '2026-10-25', '2026-10-26');

        self::assertTrue(Availability::covers(self::at('2026-10-25T21:00:00Z'), self::at('2026-10-26T00:00:00Z'), [$rule], []));
        self::assertFalse(Availability::covers(self::at('2026-11-01T21:00:00Z'), self::at('2026-11-02T00:00:00Z'), [$rule], []));
    }

    public function testAmbiguousDaylightSavingWindowsDoNotSilentlyGrantAvailability(): void
    {
        $rule = new AvailabilityRule(7, '01:30:00', '03:00:00', 'America/New_York', '2026-01-01', '2027-01-01');

        self::assertFalse(Availability::covers(self::at('2026-11-01T05:30:00Z'), self::at('2026-11-01T07:30:00Z'), [$rule], []));
    }

    public function testNonexistentDaylightSavingTimesDoNotGrantAvailability(): void
    {
        $rule = new AvailabilityRule(7, '02:30:00', '04:00:00', 'America/New_York', '2026-01-01', '2027-01-01');

        self::assertFalse(Availability::covers(self::at('2026-03-08T07:30:00Z'), self::at('2026-03-08T08:00:00Z'), [$rule], []));
    }

    public function testRejectsAnUnknownTimeZoneAndAnEmptyEffectivePeriod(): void
    {
        try {
            new AvailabilityRule(1, '09:00:00', '10:00:00', 'Mars/Base', '2026-01-01', '2027-01-01');
            self::fail('Expected an invalid zone.');
        } catch (VolunteerException $invalid) {
            self::assertSame('Choose a valid time zone.', $invalid->getMessage());
        }
        $this->expectException(VolunteerException::class);
        new AvailabilityRule(1, '09:00:00', '10:00:00', 'UTC', '2026-01-01', '2026-01-01');
    }

    private static function at(string $instant): DateTimeImmutable
    {
        return new DateTimeImmutable($instant);
    }
}
