<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Command\CheckInEventPerson\CheckInEventPersonCommand;
use App\Events\Application\Command\CheckInEventPerson\CheckInEventPersonHandler;
use App\Events\Application\Command\CorrectEventAttendance\CorrectEventAttendanceCommand;
use App\Events\Application\Command\CorrectEventAttendance\CorrectEventAttendanceHandler;
use App\Events\Application\Command\RegisterEventWalkIn\RegisterEventWalkInCommand;
use App\Events\Application\Command\RegisterEventWalkIn\RegisterEventWalkInHandler;
use App\Events\Application\EventAttendance;
use App\Events\Application\EventRegistrations;
use App\Events\Domain\EventDefinition;
use App\Events\Domain\EventException;
use App\Events\Domain\EventOccurrence;
use App\Events\Domain\EventRegistration;
use App\Events\Domain\EventSchedule;
use App\Events\Domain\EventSeries;
use App\Events\Domain\RegistrationPolicy;
use App\Events\Domain\TimeOfDay;
use App\Events\Domain\Venue;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Domain\CalendarDate;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class EventAttendanceTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string EVENT = '00000000-0000-4000-8000-0000000000e1';
    private const string OCCURRENCE = '00000000-0000-4000-8000-0000000000c1';
    private const string ADA = '00000000-0000-4000-8000-0000000000f1';
    private const string BEN = '00000000-0000-4000-8000-0000000000f2';

    private LockLog $log;
    private InMemoryEventRepository $events;
    private InMemoryOccurrenceStore $occurrences;
    private InMemoryRegistrationRepository $registrations;
    private InMemoryAttendance $attendance;
    private RecordingPeople $people;
    private RecordingJournal $journal;
    private MockClock $clock;
    private SequentialIds $ids;
    private string $booking;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->events = new InMemoryEventRepository($this->log);
        $this->occurrences = new InMemoryOccurrenceStore($this->log);
        $this->registrations = new InMemoryRegistrationRepository();
        $this->attendance = new InMemoryAttendance();
        $this->people = new RecordingPeople($this->log);
        $this->journal = new RecordingJournal();
        $this->clock = new MockClock('2026-07-05 08:00:00 UTC');
        $this->ids = new SequentialIds();
        $this->people->add(self::ADA);
        $this->people->add(self::BEN);
        $venue = Venue::create('00000000-0000-4000-8000-0000000000a1', 'Hall', '', false);
        $series = EventSeries::create('00000000-0000-4000-8000-0000000000e2', self::EVENT, 'UTC', 'ONCE', 1, 1, CalendarDate::parse('2026-07-05'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:30'), 0, $venue->id);
        $this->events->events[self::EVENT] = EventDefinition::create(self::EVENT, 'Service', 'SERVICE', '');
        $this->occurrences->insert(EventOccurrence::schedule(self::OCCURRENCE, $series, EventSchedule::preview($series)[0], $venue));
        $this->registrations->insertPolicy(RegistrationPolicy::initial(self::OCCURRENCE)->configure(true, null, true));
        $this->booking = '00000000-0000-4000-8000-0000000000b1';
        $this->registrations->insertRegistration(EventRegistration::register($this->booking, self::OCCURRENCE, self::ADA, false, 1, $this->clock->now()));
    }

    private function operations(): EventAttendance
    {
        $bookings = new EventRegistrations($this->events, $this->occurrences, $this->registrations, $this->registrations, $this->attendance, $this->people, $this->journal, $this->journal, $this->ids, $this->clock);

        return new EventAttendance($this->attendance, $this->attendance, $this->registrations, $this->occurrences, $this->events, $this->people, $bookings, $this->journal, $this->journal, $this->ids, $this->clock);
    }

    private function checkIn(int $version = 0, ?string $request = null, bool $manage = true): string
    {
        return (new CheckInEventPersonHandler(new AllowedPermissions($manage ? ['events.attendance.manage'] : ['events.attendance.read']), $this->operations()))
            ->handle(new CheckInEventPersonCommand(self::ACTOR, $request ?? '00000000-0000-4000-8000-0000000000d1', $this->booking, $version));
    }

    private function correct(string $status, int $version = 0, string $reason = 'Mistake'): void
    {
        (new CorrectEventAttendanceHandler(new AllowedPermissions(['events.attendance.manage']), $this->operations()))
            ->handle(new CorrectEventAttendanceCommand(self::ACTOR, $this->booking, $version, $status, $reason));
    }

    public function testCheckInOpensTwoHoursBeforeTheStartAndLocksPersonThenOccurrence(): void
    {
        $this->clock->modify('2026-07-05 07:59:59 UTC');
        try {
            $this->checkIn();
            self::fail('Expected the window to be closed.');
        } catch (EventException $e) {
            self::assertSame('Check-in is available from two hours before a scheduled occurrence until its end.', $e->getMessage());
        }
        $this->clock->modify('2026-07-05 08:00:00 UTC');
        $this->log->locks = [];

        $id = $this->checkIn();

        self::assertSame(['person:'.self::ADA, 'occurrence:'.self::OCCURRENCE], $this->log->locks);
        self::assertSame('PRESENT', $this->attendance->rows[$this->booking]->status);
        self::assertSame($id, $this->attendance->rows[$this->booking]->id);
        self::assertSame(['CHECKED_IN:Arrival recorded.'], $this->attendance->history);
        self::assertArrayHasKey('00000000-0000-4000-8000-0000000000d1', $this->journal->requests);
    }

    public function testCheckInClosesWhenTheOccurrenceEnds(): void
    {
        $this->clock->modify('2026-07-05 11:29:59 UTC');
        $this->checkIn();

        $this->attendance->rows = [];
        $this->clock->modify('2026-07-05 11:30:00 UTC');
        $this->expectExceptionMessage('Check-in is available from two hours before');

        $this->checkIn(request: '00000000-0000-4000-8000-0000000000d2');
    }

    public function testCheckInChecksPermissionFirstAndTheBookingVersion(): void
    {
        try {
            $this->checkIn(manage: false);
            self::fail('Expected a permission failure.');
        } catch (IdentityException) {
            self::assertSame([], $this->log->locks);
        }
        $this->expectExceptionMessage('These details changed. Refresh and review before saving.');

        $this->checkIn(version: 3);
    }

    public function testASecondCheckInIsRefusedButARepeatedRequestReturnsTheFirst(): void
    {
        $first = $this->checkIn();

        self::assertSame($first, $this->checkIn(), 'a repeated request id is idempotent');
        try {
            $this->checkIn(request: '00000000-0000-4000-8000-0000000000d9');
            self::fail('Expected a conflict.');
        } catch (EventException $e) {
            self::assertSame('Attendance already exists. Use its versioned correction action.', $e->getMessage());
        }
        self::assertCount(1, $this->attendance->rows);
        $this->expectExceptionMessage('This request identifier was already used.');
        $this->checkIn(version: 1);
    }

    public function testOnlyConfirmedBookingsOfAvailablePeopleOnActiveEventsCanCheckIn(): void
    {
        $this->registrations->saveRegistration($this->registrations->rows[$this->booking]->cancel($this->clock->now()));
        try {
            $this->checkIn(version: 1);
            self::fail('A cancelled booking cannot check in.');
        } catch (EventException $e) {
            self::assertStringStartsWith('Check-in requires a confirmed booking', $e->getMessage());
        }
        $this->registrations->saveRegistration($this->registrations->rows[$this->booking]->rejoin(false, 2, $this->clock->now()));
        $this->people->add(self::ADA, available: false);
        try {
            $this->checkIn(version: 2);
            self::fail('An unavailable person cannot check in.');
        } catch (EventException) {
            self::assertSame([], $this->attendance->rows);
        }
    }

    public function testACorrectionVoidsAndReinstatesWithHistoryAndOnlyWhenTheStateChanges(): void
    {
        $this->checkIn();

        $this->correct('VOIDED');
        self::assertSame('VOIDED', $this->attendance->rows[$this->booking]->status);
        self::assertSame(1, $this->attendance->rows[$this->booking]->version);
        $this->correct('VOIDED', 1);
        self::assertSame(1, $this->attendance->rows[$this->booking]->version, 'no change, no new version');
        $this->correct('PRESENT', 1);
        self::assertSame(['CHECKED_IN:Arrival recorded.', 'VOIDED:Mistake', 'REINSTATED:Mistake'], $this->attendance->history);
    }

    public function testCorrectionsNeedAnExistingRecordTheVersionAValidStatusAndAReason(): void
    {
        try {
            $this->correct('VOIDED');
            self::fail('There is no attendance to correct.');
        } catch (EventException $e) {
            self::assertSame('Attendance was not found.', $e->getMessage());
        }
        $this->checkIn();
        foreach ([['VOIDED', 5, 'These details changed. Refresh and review before saving.'], ['GONE', 0, 'Choose present or voided attendance.'], ['VOIDED', 0, 'Enter text of 1–1000 characters.']] as [$status, $version, $message]) {
            try {
                $this->correct($status, $version, 'Enter text of 1–1000 characters.' === $message ? ' ' : 'Mistake');
                self::fail($message);
            } catch (EventException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
        self::assertSame('PRESENT', $this->attendance->rows[$this->booking]->status);
    }

    public function testReinstatingAfterTheEndSkipsTheAdmissionWindowButBeforeItRequiresIt(): void
    {
        $this->checkIn();
        $this->correct('VOIDED');
        $this->clock->modify('2026-07-05 12:00:00 UTC');
        $this->correct('PRESENT', 1);
        self::assertSame('PRESENT', $this->attendance->rows[$this->booking]->status);

        $this->correct('VOIDED', 2);
        $this->clock->modify('2026-07-05 07:00:00 UTC');
        $this->expectExceptionMessage('Check-in is available from two hours before');

        $this->correct('PRESENT', 3);
    }

    public function testAWalkInBooksAndAdmitsAConfirmedPersonInOneStep(): void
    {
        $this->clock->modify('2026-07-05 09:00:00 UTC');
        $handler = new RegisterEventWalkInHandler(new AllowedPermissions(['events.attendance.manage']), $this->operations());

        $walkIn = $handler->handle(new RegisterEventWalkInCommand(self::ACTOR, '00000000-0000-4000-8000-0000000000d5', self::OCCURRENCE, self::BEN, null));

        self::assertTrue($walkIn->admitted);
        self::assertSame('CONFIRMED', $walkIn->status);
        self::assertSame('PRESENT', $this->attendance->rows[$walkIn->registrationId]->status);
        $again = $handler->handle(new RegisterEventWalkInCommand(self::ACTOR, '00000000-0000-4000-8000-0000000000d5', self::OCCURRENCE, self::BEN, null));
        self::assertSame($walkIn->registrationId, $again->registrationId);
        self::assertCount(1, $this->attendance->rows, 'a repeat does not admit twice');
    }

    public function testAWalkInOnAFullOccurrenceIsWaitlistedAndNotAdmitted(): void
    {
        $this->clock->modify('2026-07-05 09:00:00 UTC');
        $this->registrations->savePolicy($this->registrations->policies[self::OCCURRENCE]->configure(true, 1, true));

        $walkIn = (new RegisterEventWalkInHandler(new AllowedPermissions(['events.attendance.manage']), $this->operations()))
            ->handle(new RegisterEventWalkInCommand(self::ACTOR, '00000000-0000-4000-8000-0000000000d6', self::OCCURRENCE, self::BEN, null));

        self::assertFalse($walkIn->admitted);
        self::assertSame('WAITLISTED', $walkIn->status);
        self::assertSame([], $this->attendance->rows);
    }

    public function testAWalkInOutsideTheWindowIsRefused(): void
    {
        $this->clock->modify('2026-07-05 07:00:00 UTC');
        $this->expectExceptionMessage('Check-in is available from two hours before');

        (new RegisterEventWalkInHandler(new AllowedPermissions(['events.attendance.manage']), $this->operations()))
            ->handle(new RegisterEventWalkInCommand(self::ACTOR, '00000000-0000-4000-8000-0000000000d7', self::OCCURRENCE, self::BEN, null));
    }
}
