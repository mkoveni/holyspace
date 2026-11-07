<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Command\CancelEventRegistration\CancelEventRegistrationCommand;
use App\Events\Application\Command\CancelEventRegistration\CancelEventRegistrationHandler;
use App\Events\Application\Command\ConfigureEventRegistration\ConfigureEventRegistrationCommand;
use App\Events\Application\Command\ConfigureEventRegistration\ConfigureEventRegistrationHandler;
use App\Events\Application\Command\RegisterEventPerson\RegisterEventPersonCommand;
use App\Events\Application\Command\RegisterEventPerson\RegisterEventPersonHandler;
use App\Events\Application\EventRegistrations;
use App\Events\Domain\EventDefinition;
use App\Events\Domain\EventException;
use App\Events\Domain\EventOccurrence;
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

final class EventRegistrationsTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string EVENT = '00000000-0000-4000-8000-0000000000e1';
    private const string OCCURRENCE = '00000000-0000-4000-8000-0000000000c1';
    private const string ADA = '00000000-0000-4000-8000-0000000000f1';
    private const string BEN = '00000000-0000-4000-8000-0000000000f2';
    private const string CARA = '00000000-0000-4000-8000-0000000000f3';

    private LockLog $log;
    private InMemoryEventRepository $events;
    private InMemoryOccurrenceStore $occurrences;
    private InMemoryRegistrationRepository $registrations;
    private FixedAttendance $attendance;
    private RecordingPeople $people;
    private RecordingJournal $journal;
    private MockClock $clock;
    private int $request = 0;
    private SequentialIds $ids;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->events = new InMemoryEventRepository($this->log);
        $this->occurrences = new InMemoryOccurrenceStore($this->log);
        $this->registrations = new InMemoryRegistrationRepository();
        $this->attendance = new FixedAttendance();
        $this->people = new RecordingPeople($this->log);
        $this->journal = new RecordingJournal();
        $this->clock = new MockClock('2026-06-01 10:00:00 UTC');
        $this->ids = new SequentialIds();
        foreach ([self::ADA, self::BEN, self::CARA] as $id) {
            $this->people->add($id);
        }
        $venue = Venue::create('00000000-0000-4000-8000-0000000000a1', 'Hall', '', false);
        $series = EventSeries::create('00000000-0000-4000-8000-0000000000e2', self::EVENT, 'UTC', 'ONCE', 1, 1, CalendarDate::parse('2026-07-05'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:30'), 0, $venue->id);
        $this->events->events[self::EVENT] = EventDefinition::create(self::EVENT, 'Service', 'SERVICE', '');
        $this->occurrences->insert(EventOccurrence::schedule(self::OCCURRENCE, $series, EventSchedule::preview($series)[0], $venue));
        $this->registrations->insertPolicy(RegistrationPolicy::initial(self::OCCURRENCE));
    }

    private function operations(): EventRegistrations
    {
        return new EventRegistrations($this->events, $this->occurrences, $this->registrations, $this->registrations, $this->attendance, $this->people, $this->journal, $this->journal, $this->ids, $this->clock);
    }

    private function configure(bool $open, ?int $capacity = null, bool $waitlist = true, int $version = -1, bool $manage = true): void
    {
        $version = $version >= 0 ? $version : $this->registrations->policies[self::OCCURRENCE]->version;
        (new ConfigureEventRegistrationHandler(new AllowedPermissions($manage ? ['events.registrations.manage'] : ['events.registrations.read']), $this->operations()))
            ->handle(new ConfigureEventRegistrationCommand(self::ACTOR, self::OCCURRENCE, $version, $open, $capacity, $waitlist, 'Planning'));
    }

    private function register(string $person, ?int $version = null, ?string $request = null, bool $manage = true): string
    {
        return (new RegisterEventPersonHandler(new AllowedPermissions($manage ? ['events.registrations.manage'] : ['events.registrations.read']), $this->operations()))
            ->handle(new RegisterEventPersonCommand(self::ACTOR, $request ?? sprintf('00000000-0000-4000-8000-%012d', ++$this->request), self::OCCURRENCE, $person, $version));
    }

    private function cancel(string $registration, int $version = 0): void
    {
        (new CancelEventRegistrationHandler(new AllowedPermissions(['events.registrations.manage']), $this->operations()))
            ->handle(new CancelEventRegistrationCommand(self::ACTOR, $registration, $version, 'Cannot attend'));
    }

    private function stateOf(string $registration): string
    {
        return $this->registrations->rows[$registration]->status;
    }

    public function testConfiguringChecksThePermissionVersionAndCapacityRules(): void
    {
        try {
            $this->configure(true, manage: false);
            self::fail('Expected a permission failure.');
        } catch (IdentityException) {
            self::assertSame([], $this->log->locks);
        }
        try {
            $this->configure(true, version: 4);
            self::fail('Expected a stale version.');
        } catch (EventException) {
            self::assertFalse($this->registrations->policies[self::OCCURRENCE]->open);
        }
        $this->configure(true, 2);
        $policy = $this->registrations->policies[self::OCCURRENCE];
        self::assertTrue($policy->open);
        self::assertSame(2, $policy->capacity);
        self::assertSame(1, $policy->version);
        self::assertContains(self::ACTOR.':registration.policy.updated:'.self::OCCURRENCE, $this->journal->entries);
    }

    public function testCapacityMustBeInRangeAndNotBelowConfirmedBookings(): void
    {
        foreach ([0, 10001] as $capacity) {
            try {
                $this->configure(true, $capacity);
                self::fail('Expected a capacity failure.');
            } catch (EventException $e) {
                self::assertSame('Choose a capacity of 1–10,000, or leave it unlimited.', $e->getMessage());
            }
        }
        $this->configure(true, 2);
        $this->register(self::ADA);
        $this->register(self::BEN);

        $this->expectExceptionMessage('Capacity cannot be below the confirmed registrations.');

        $this->configure(true, 1);
    }

    public function testRegistrationMustBeOpenToBook(): void
    {
        $this->expectExceptionMessage('Registration is closed.');

        $this->register(self::ADA);
    }

    public function testBookingLocksThePersonThenTheOccurrenceAndAllocatesQueuePositions(): void
    {
        $this->configure(true, 1);
        $this->log->locks = [];

        $ada = $this->register(self::ADA);

        self::assertSame(['person:'.self::ADA, 'occurrence:'.self::OCCURRENCE], $this->log->locks);
        $ben = $this->register(self::BEN);
        self::assertSame('CONFIRMED', $this->stateOf($ada));
        self::assertSame('WAITLISTED', $this->stateOf($ben));
        self::assertSame(1, $this->registrations->rows[$ada]->position);
        self::assertSame(2, $this->registrations->rows[$ben]->position);
        self::assertSame(2, $this->registrations->policies[self::OCCURRENCE]->nextPosition);
        self::assertContains(self::ACTOR.":registration.REGISTERED:$ben:WAITLISTED", $this->journal->entries);
    }

    public function testAFullOccurrenceWithoutAWaitlistRefusesBookings(): void
    {
        $this->configure(true, 1, waitlist: false);
        $this->register(self::ADA);

        $this->expectExceptionMessage('The occurrence is full and its waitlist is disabled.');

        $this->register(self::BEN);
    }

    public function testCancellingPromotesTheWaitlistInQueueOrder(): void
    {
        $this->configure(true, 1);
        $ada = $this->register(self::ADA);
        $ben = $this->register(self::BEN);
        $cara = $this->register(self::CARA);
        $this->log->locks = [];

        $this->cancel($ada);

        self::assertSame(['person:'.self::ADA, 'occurrence:'.self::OCCURRENCE], $this->log->locks);
        self::assertSame('CANCELLED', $this->stateOf($ada));
        self::assertSame('CONFIRMED', $this->stateOf($ben), 'the earliest waitlisted person is promoted first');
        self::assertSame('WAITLISTED', $this->stateOf($cara));
        self::assertContains(self::ACTOR.":registration.CANCELLED:$ada:Cannot attend", $this->journal->entries);
        self::assertSame(5, $this->registrations->policies[self::OCCURRENCE]->version, 'cancelling touches the policy version');
    }

    public function testRaisingCapacityPromotesWaitlistedPeople(): void
    {
        $this->configure(true, 1);
        $this->register(self::ADA);
        $ben = $this->register(self::BEN);

        $this->configure(true, 2);

        self::assertSame('CONFIRMED', $this->stateOf($ben));
    }

    public function testACancelledPersonRejoinsTheSameRowAtTheBackOfTheQueue(): void
    {
        $this->configure(true, 5);
        $ada = $this->register(self::ADA);
        $this->register(self::BEN);
        $this->cancel($ada);

        try {
            $this->register(self::ADA);
            self::fail('A person with a record must send its version.');
        } catch (EventException $e) {
            self::assertSame('This person already has a record. Use its versioned re-registration action.', $e->getMessage());
        }
        try {
            $this->register(self::ADA, 0);
            self::fail('Expected a stale version.');
        } catch (EventException) {
            self::assertSame('CANCELLED', $this->stateOf($ada));
        }
        $again = $this->register(self::ADA, 1);

        self::assertSame($ada, $again, 'the cancelled row is reused');
        self::assertSame('CONFIRMED', $this->stateOf($ada));
        self::assertSame(3, $this->registrations->rows[$ada]->position);
        self::assertSame(2, $this->registrations->rows[$ada]->revision->version);
    }

    public function testBookingTwiceIsRefused(): void
    {
        $this->configure(true);
        $ada = $this->register(self::ADA);

        $this->expectExceptionMessage('This person is already registered.');

        $this->register(self::ADA, $this->registrations->rows[$ada]->revision->version);
    }

    public function testARepeatedRequestReturnsTheSameRegistrationAndADifferentOneConflicts(): void
    {
        $this->configure(true);
        $request = '00000000-0000-4000-8000-0000000000b1';
        $first = $this->register(self::ADA, request: $request);

        self::assertSame($first, $this->register(self::ADA, request: $request));
        self::assertCount(1, $this->registrations->rows);
        try {
            $this->register(self::BEN, request: $request);
            self::fail('Expected a conflict.');
        } catch (EventException $e) {
            self::assertSame('The registration request identifier was already used.', $e->getMessage());
        }
    }

    public function testUnavailableOrUnknownPeopleCannotBook(): void
    {
        $this->configure(true);
        $this->people->add(self::CARA, available: false);
        foreach ([self::CARA, '00000000-0000-4000-8000-0000000000f9'] as $person) {
            try {
                $this->register($person);
                self::fail('Expected a missing person.');
            } catch (EventException $e) {
                self::assertSame('Available person was not found.', $e->getMessage());
            }
        }
        self::assertSame([], $this->registrations->rows);
    }

    public function testStartedCancelledAndArchivedOccurrencesCannotBeBooked(): void
    {
        $this->configure(true);
        $this->clock->modify('2026-07-05 10:30:00 UTC');
        try {
            $this->register(self::ADA);
            self::fail('Expected a started occurrence to be refused.');
        } catch (EventException $e) {
            self::assertSame('Registration requires an active event and a scheduled occurrence that has not started.', $e->getMessage());
        }
        $this->clock->modify('2026-06-01 10:00:00 UTC');
        $this->events->events[self::EVENT] = EventDefinition::reconstitute(self::EVENT, 'Service', 'SERVICE', '', false, 0);
        $this->expectExceptionMessage('Registration requires an active event');

        $this->register(self::ADA);
    }

    public function testCancellingNeedsTheCurrentVersionAndRefusesPresentPeopleAndHistory(): void
    {
        $this->configure(true);
        $ada = $this->register(self::ADA);
        try {
            $this->cancel($ada, 5);
            self::fail('Expected a stale version.');
        } catch (EventException) {
            self::assertSame('CONFIRMED', $this->stateOf($ada));
        }
        $this->attendance->present[] = $ada;
        try {
            $this->cancel($ada);
            self::fail('Expected a present person to block cancelling.');
        } catch (EventException $e) {
            self::assertSame("Correct the person's attendance before cancelling this booking.", $e->getMessage());
        }
        $this->attendance->present = [];
        $this->clock->modify('2026-07-05 12:00:00 UTC');
        $this->expectExceptionMessage('Historical registration cannot be rewritten.');
        $this->cancel($ada);
    }

    public function testCancellingTwiceIsANoOp(): void
    {
        $this->configure(true);
        $ada = $this->register(self::ADA);
        $this->cancel($ada);
        $entries = \count($this->journal->entries);

        $this->cancel($ada, 1);

        self::assertCount($entries, $this->journal->entries);
    }

    public function testBookingAtTheDoorNeedsTheAdmissionWindow(): void
    {
        $this->configure(true);
        $this->clock->modify('2026-07-05 07:30:00 UTC');
        $this->expectExceptionMessage('Check-in is available from two hours before a scheduled occurrence until its end.');

        $this->operations()->register(self::ACTOR, '00000000-0000-4000-8000-0000000000b9', self::OCCURRENCE, self::ADA, null, true);
    }

    public function testBookingAtTheDoorWorksInsideTheWindowEvenAfterTheStart(): void
    {
        $this->configure(true);
        $this->clock->modify('2026-07-05 10:30:00 UTC');

        $id = $this->operations()->register(self::ACTOR, '00000000-0000-4000-8000-0000000000b9', self::OCCURRENCE, self::ADA, null, true);

        self::assertSame('CONFIRMED', $this->stateOf($id));
    }
}
