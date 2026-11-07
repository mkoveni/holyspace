<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Domain;

use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\SessionSeries;
use App\ChildrenMinistry\Sessions\Domain\WeeklyRecurrence;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionSeriesTest extends TestCase
{
    private function series(): SessionSeries
    {
        $source = MinistrySession::plan('s', 'c', 'r', new DateTimeImmutable('2026-06-07T09:00:00Z'), new DateTimeImmutable('2026-06-07T11:00:00Z'), 8);

        return SessionSeries::create('id', '  Term 3  ', $source, 'Africa/Johannesburg', new WeeklyRecurrence(CalendarDate::parse('2026-06-14'), '09:00', '10:00', 1, 3), new DateTimeImmutable('2026-06-01T00:00:00Z'), 'actor', 'request', 'fingerprint');
    }

    public function testCreationCopiesTheSourceAndTrimsTheName(): void
    {
        $series = $this->series();

        self::assertSame('Term 3', $series->name);
        self::assertSame(['c', 'r', 8, 0, false], [$series->classId, $series->roomId, $series->seatLimit, $series->version, $series->stopped]);
    }

    public function testStoppingAdvancesTheVersionOnce(): void
    {
        $stopped = $this->series()->stop(0);

        self::assertTrue($stopped->stopped);
        self::assertSame(1, $stopped->version);
        $this->expectException(ChildrenException::class);
        $this->expectExceptionMessage('already been cancelled');
        $stopped->stop(1);
    }

    public function testAStaleVersionIsAConflict(): void
    {
        $this->expectException(ChildrenException::class);
        $this->series()->stop(3);
    }

    public function testABlankNameIsRejected(): void
    {
        $this->expectException(ChildrenException::class);
        $source = MinistrySession::plan('s', 'c', 'r', new DateTimeImmutable('2026-06-07T09:00:00Z'), new DateTimeImmutable('2026-06-07T11:00:00Z'), 8);
        SessionSeries::create('id', ' ', $source, 'UTC', new WeeklyRecurrence(CalendarDate::parse('2026-06-14'), '09:00', '10:00', 1, 1), new DateTimeImmutable('2026-06-01T00:00:00Z'), 'a', 'r', 'f');
    }
}
