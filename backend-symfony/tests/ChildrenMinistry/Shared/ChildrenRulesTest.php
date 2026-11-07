<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Shared;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChildrenRulesTest extends TestCase
{
    public function testUnknownAgeIsNeverAdultOrChild(): void
    {
        $date = CalendarDate::parse('2026-10-26');

        foreach ([ChildrenRules::adult(...), ChildrenRules::child(...)] as $rule) {
            try {
                $rule(null, $date);
                self::fail('Expected a rejection.');
            } catch (ChildrenException $error) {
                self::assertSame(ErrorKind::Invalid, $error->kind());
            }
        }
    }

    public function testEighteenthBirthdayEndsChildEligibilityIncludingLeapBirthdays(): void
    {
        $birthday = CalendarDate::parse('2008-02-29');

        ChildrenRules::child($birthday, CalendarDate::parse('2026-02-27'));
        ChildrenRules::adult($birthday, CalendarDate::parse('2026-02-28'));
        $this->expectException(ChildrenException::class);
        ChildrenRules::child($birthday, CalendarDate::parse('2026-02-28'));
    }

    public function testFutureBirthDatesIdentifyNeitherChildNorAdult(): void
    {
        foreach ([ChildrenRules::child(...), ChildrenRules::adult(...)] as $rule) {
            try {
                $rule(CalendarDate::parse('2026-10-27'), CalendarDate::parse('2026-10-26'));
                self::fail('Expected a rejection.');
            } catch (ChildrenException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** @return iterable<string, array{?string, int, bool}> */
    public static function texts(): iterable
    {
        yield 'trimmed' => ['  Juniors ', 120, true];
        yield 'at the limit' => [str_repeat('é', 120), 120, true];
        yield 'over the limit' => [str_repeat('é', 121), 120, false];
        yield 'blank' => ['   ', 120, false];
        yield 'missing' => [null, 120, false];
    }

    #[DataProvider('texts')]
    public function testTextIsRequiredTrimmedAndBounded(?string $value, int $max, bool $valid): void
    {
        if ($valid) {
            self::assertSame(trim((string) $value), ChildrenRules::text($value, $max));

            return;
        }
        $this->expectException(ChildrenException::class);
        ChildrenRules::text($value, $max);
    }

    public function testOptionalTextMayBeBlankButNotOversized(): void
    {
        self::assertSame('', ChildrenRules::optional(null, 10));
        self::assertSame('', ChildrenRules::optional('  ', 10));
        self::assertSame('ok', ChildrenRules::optional(' ok ', 10));
        $this->expectException(ChildrenException::class);
        ChildrenRules::optional(str_repeat('x', 11), 10);
    }

    public function testPeriodsMustEndAfterTheyStart(): void
    {
        ChildrenRules::period(CalendarDate::parse('2026-01-01'), CalendarDate::parse('2026-01-02'));

        foreach ([['2026-01-01', '2026-01-01'], ['2026-01-02', '2026-01-01']] as [$from, $until]) {
            try {
                ChildrenRules::period(CalendarDate::parse($from), CalendarDate::parse($until));
                self::fail('Expected a rejection.');
            } catch (ChildrenException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(ChildrenException::class);
        ChildrenRules::period(null, CalendarDate::parse('2026-01-01'));
    }

    public function testVersionsMustMatchAndBeNonNegative(): void
    {
        ChildrenRules::version(3, 3);

        foreach ([[3, 2], [3, 4], [0, -1]] as [$actual, $expected]) {
            try {
                ChildrenRules::version($actual, $expected);
                self::fail('Expected a conflict.');
            } catch (ChildrenException $error) {
                self::assertSame(ErrorKind::Conflict, $error->kind());
            }
        }
    }

    public function testCoverageIsStartInclusiveEndExclusiveOnTheStartOnly(): void
    {
        $start = new DateTimeImmutable('2026-01-01T10:00:00Z');
        $end = new DateTimeImmutable('2026-01-01T12:00:00Z');

        self::assertTrue(ChildrenRules::covers($start, $end, $start, $end));
        self::assertTrue(ChildrenRules::covers($start, $end, $start->modify('+30 minutes'), $start->modify('+1 hour')));
        self::assertFalse(ChildrenRules::covers($start, $end, $start->modify('-1 minute'), $end));
        self::assertFalse(ChildrenRules::covers($start, $end, $start, $end->modify('+1 minute')));
        self::assertFalse(ChildrenRules::covers($start, $end, $end, $end->modify('+1 hour')), 'a window starting at the end is outside');
    }

    public function testPagingIsBounded(): void
    {
        ChildrenRules::page(1, 0);
        ChildrenRules::page(100, 1000000);

        foreach ([[0, 0], [101, 0], [10, -1], [10, 1000001]] as [$limit, $offset]) {
            try {
                ChildrenRules::page($limit, $offset);
                self::fail('Expected a rejection.');
            } catch (ChildrenException $error) {
                self::assertSame('Invalid paging parameters.', $error->getMessage());
            }
        }
    }
}
