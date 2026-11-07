<?php

declare(strict_types=1);

namespace App\Tests\Events\Domain;

use App\Events\Domain\EventException;
use App\Events\Domain\EventOccurrence;
use App\Events\Domain\EventSchedule;
use App\Events\Domain\EventSeries;
use App\Events\Domain\OccurrenceCloseout;
use App\Events\Domain\TimeOfDay;
use App\Events\Domain\Venue;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OccurrenceCloseoutTest extends TestCase
{
    private const string ID = '00000000-0000-4000-8000-0000000000c1';
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';

    private function occurrence(bool $cancelled = false): EventOccurrence
    {
        $venue = Venue::create('00000000-0000-4000-8000-0000000000a1', 'Hall', '', false);
        $series = EventSeries::create('00000000-0000-4000-8000-0000000000e2', '00000000-0000-4000-8000-0000000000e1', 'UTC', 'ONCE', 1, 1, CalendarDate::parse('2026-07-05'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:00'), 0, $venue->id);
        $occurrence = EventOccurrence::schedule(self::ID, $series, EventSchedule::preview($series)[0], $venue);

        return $cancelled ? $occurrence->cancel() : $occurrence;
    }

    private function close(OccurrenceCloseout $closeout, string $now = '2026-07-05 11:00:00', bool $unresolved = false, string $followUp = '', bool $reviewed = true, int $version = 0, ?EventOccurrence $occurrence = null): OccurrenceCloseout
    {
        return $closeout->close($occurrence ?? $this->occurrence(), $version, str_repeat('a', 64), 'All good', $followUp, $reviewed, $reviewed, $unresolved, self::ACTOR, new DateTimeImmutable($now.' UTC'));
    }

    public function testANewCloseoutIsOpenAtVersionZero(): void
    {
        $open = OccurrenceCloseout::open(self::ID);

        self::assertSame('OPEN', $open->status);
        self::assertSame(0, $open->version);
        self::assertNull($open->updatedBy);
    }

    public function testClosingRecordsTheReviewAndAdvancesTheVersion(): void
    {
        $closed = $this->close(OccurrenceCloseout::open(self::ID));

        self::assertSame('CLOSED', $closed->status);
        self::assertSame(1, $closed->version);
        self::assertSame(str_repeat('a', 64), $closed->reviewHash);
        self::assertSame('All good', $closed->note);
        self::assertSame(self::ACTOR, $closed->updatedBy);
    }

    public function testAScheduledOccurrenceCannotBeClosedBeforeItEndsButACancelledOneCan(): void
    {
        $open = OccurrenceCloseout::open(self::ID);
        try {
            $this->close($open, '2026-07-05 10:59:59');
            self::fail('Expected a conflict.');
        } catch (EventException $e) {
            self::assertSame('Close out after the gathering ends, or after it is cancelled.', $e->getMessage());
        }

        self::assertSame('CLOSED', $this->close($open, '2026-07-05 09:00:00', occurrence: $this->occurrence(cancelled: true))->status);
    }

    public function testBothAcknowledgementsAreRequired(): void
    {
        $this->expectExceptionMessage('Review attendance, waitlists and delivery outcomes before closing out.');

        $this->close(OccurrenceCloseout::open(self::ID), reviewed: false);
    }

    public function testFollowUpIsMandatoryOnlyWhenSomethingIsUnresolved(): void
    {
        $open = OccurrenceCloseout::open(self::ID);
        self::assertSame('', $this->close($open)->followUp);
        self::assertSame('Call them', $this->close($open, unresolved: true, followUp: 'Call them')->followUp);

        $this->expectExceptionMessage('Enter text of 1–2000 characters.');

        $this->close($open, unresolved: true);
    }

    public function testTheVersionIsCheckedAndAClosedOccurrenceCannotBeClosedAgain(): void
    {
        try {
            $this->close(OccurrenceCloseout::open(self::ID), version: 3);
            self::fail('Expected a stale version.');
        } catch (EventException $e) {
            self::assertSame('These details changed. Refresh and review before saving.', $e->getMessage());
        }
        $this->expectExceptionMessage('This occurrence is already closed out.');

        $this->close($this->close(OccurrenceCloseout::open(self::ID)), version: 1);
    }

    public function testReopeningNeedsAClosedStateTheVersionAndAReasonAndKeepsTheReviewHash(): void
    {
        $closed = $this->close(OccurrenceCloseout::open(self::ID));
        $reopened = $closed->reopen(1, 'Late correction', self::ACTOR, new DateTimeImmutable('2026-07-06T09:00:00Z'));

        self::assertSame('OPEN', $reopened->status);
        self::assertSame(2, $reopened->version);
        self::assertSame('Late correction', $reopened->note);
        self::assertSame('', $reopened->followUp);
        self::assertSame($closed->reviewHash, $reopened->reviewHash);
        foreach ([[$closed, 9, 'x', 'These details changed. Refresh and review before saving.'], [$closed, 1, ' ', 'Enter text of 1–2000 characters.'], [OccurrenceCloseout::open(self::ID), 0, 'x', 'Only a closed occurrence can be reopened for review.']] as [$from, $version, $reason, $message]) {
            try {
                $from->reopen($version, $reason, self::ACTOR, new DateTimeImmutable('2026-07-06T09:00:00Z'));
                self::fail($message);
            } catch (EventException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
    }
}
