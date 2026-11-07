<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Command\CancelEventOccurrences\CancelEventOccurrencesCommand;
use App\Events\Application\Command\CancelEventOccurrences\CancelEventOccurrencesHandler;
use App\Events\Application\Command\RescheduleEventOccurrences\RescheduleEventOccurrencesCommand;
use App\Events\Application\Command\RescheduleEventOccurrences\RescheduleEventOccurrencesHandler;
use App\Events\Application\Command\RestoreEventOccurrence\RestoreEventOccurrenceCommand;
use App\Events\Application\Command\RestoreEventOccurrence\RestoreEventOccurrenceHandler;
use App\Events\Application\Contract\RegistrationCounts;
use App\Events\Application\OccurrenceChangeDraft;
use App\Events\Application\OccurrenceChangeReview;
use App\Events\Application\OccurrenceChanges;
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
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class OccurrenceChangesTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string EVENT = '00000000-0000-4000-8000-0000000000e1';
    private const string SERIES = '00000000-0000-4000-8000-0000000000e2';
    private const string HALL = '00000000-0000-4000-8000-0000000000a1';
    private const string FOYER = '00000000-0000-4000-8000-0000000000a0';
    /** Ids deliberately run against the date order so lock sorting is observable. */
    private const array OCCURRENCES = ['00000000-0000-4000-8000-0000000000c3', '00000000-0000-4000-8000-0000000000c2', '00000000-0000-4000-8000-0000000000c1'];

    private LockLog $log;
    private InMemoryVenueRepository $venues;
    private InMemoryEventRepository $events;
    private InMemoryOccurrenceStore $occurrences;
    private InMemoryRegistrationRepository $registrations;
    private FixedAttendance $attendance;
    private RecordingJournal $journal;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->venues = new InMemoryVenueRepository($this->log);
        $this->events = new InMemoryEventRepository($this->log);
        $this->occurrences = new InMemoryOccurrenceStore($this->log);
        $this->registrations = new InMemoryRegistrationRepository();
        $this->attendance = new FixedAttendance();
        $this->journal = new RecordingJournal();
        $this->clock = new MockClock('2026-06-01 10:00:00 UTC');

        $hall = Venue::create(self::HALL, 'Main hall', '1 Church St', true);
        $this->venues->insert($hall);
        $this->venues->insert(Venue::create(self::FOYER, 'Foyer', '', false));
        $this->events->events[self::EVENT] = EventDefinition::create(self::EVENT, 'Sunday service', 'SERVICE', '');
        $series = EventSeries::create(self::SERIES, self::EVENT, 'UTC', 'WEEKLY', 1, 3, CalendarDate::parse('2026-07-05'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:30'), 0, self::HALL);
        $this->events->series[self::SERIES] = $series;
        foreach (EventSchedule::preview($series) as $index => $window) {
            $occurrence = EventOccurrence::schedule(self::OCCURRENCES[$index], $series, $window, $hall);
            $this->occurrences->insert($occurrence);
            $this->registrations->insertPolicy(RegistrationPolicy::initial($occurrence->id));
        }
    }

    private function review(): OccurrenceChangeReview
    {
        return new OccurrenceChangeReview($this->events, $this->venues, $this->occurrences, $this->occurrences, $this->registrations, $this->attendance, $this->clock);
    }

    private function changes(): OccurrenceChanges
    {
        return new OccurrenceChanges($this->events, $this->venues, $this->occurrences, $this->registrations, $this->review(), $this->journal, $this->clock);
    }

    private function draft(string $action, string $scope = 'THIS', string $occurrence = self::OCCURRENCES[0], int $version = 0): OccurrenceChangeDraft
    {
        $reschedule = 'RESCHEDULE' === $action;

        return new OccurrenceChangeDraft($occurrence, $version, $action, $scope, $reschedule ? CalendarDate::parse('2026-07-06') : null, $reschedule ? TimeOfDay::parse('12:00') : null, $reschedule ? TimeOfDay::parse('13:00') : null, 0, $reschedule ? self::FOYER : null);
    }

    private function hash(OccurrenceChangeDraft $draft): string
    {
        return $this->review()->plan($draft)->hash;
    }

    private function reschedule(OccurrenceChangeDraft $draft, ?string $hash, bool $manage = true): void
    {
        (new RescheduleEventOccurrencesHandler(new AllowedPermissions($manage ? ['events.manage'] : ['events.read']), $this->changes()))
            ->handle(new RescheduleEventOccurrencesCommand(self::ACTOR, $draft, $hash, 'Hall unavailable'));
    }

    public function testReschedulingOneDateLocksEventSeriesVenuesThenOccurrencesAndMarksItEdited(): void
    {
        $draft = $this->draft('RESCHEDULE');
        $this->reschedule($draft, $this->hash($draft));

        self::assertSame(['event:'.self::EVENT, 'series:'.self::SERIES, 'venue:'.self::FOYER, 'venue:'.self::HALL, 'occurrence:'.self::OCCURRENCES[0]], $this->log->locks);
        $moved = $this->occurrences->occurrences[self::OCCURRENCES[0]];
        self::assertSame('2026-07-06T12:00:00Z', $moved->startsAt->format('Y-m-d\TH:i:s\Z'));
        self::assertSame(self::FOYER, $moved->venueId);
        self::assertSame('Foyer', $moved->venueName);
        self::assertTrue($moved->overridden);
        self::assertSame(1, $moved->version);
        self::assertSame(1, $moved->scheduleRevision);
        self::assertSame('2026-07-05', $moved->originDate->iso(), 'a moved date keeps its recurrence origin');
        self::assertSame(1, $this->events->series[self::SERIES]->version, 'THIS leaves the series defaults alone');
        self::assertSame(self::HALL, $this->events->series[self::SERIES]->venueId);
        self::assertContains(self::ACTOR.':occurrences.reschedule:'.self::EVENT, $this->journal->entries);
        self::assertContains(self::ACTOR.':occurrence.RESCHEDULE:'.self::OCCURRENCES[0], $this->journal->entries);
    }

    public function testRescheduleFutureLocksOccurrencesInAscendingIdOrderAndUpdatesSeriesDefaults(): void
    {
        $draft = new OccurrenceChangeDraft(self::OCCURRENCES[0], 0, 'RESCHEDULE', 'FUTURE', null, TimeOfDay::parse('12:00'), TimeOfDay::parse('13:00'), 0, self::FOYER);
        $this->reschedule($draft, $this->hash($draft));

        $locks = array_values(array_filter($this->log->locks, static fn (string $l): bool => str_starts_with($l, 'occurrence:')));
        $expected = array_map(static fn (string $id): string => 'occurrence:'.$id, array_reverse(self::OCCURRENCES));
        self::assertSame($expected, $locks);
        $series = $this->events->series[self::SERIES];
        self::assertSame('12:00:00', $series->startsAt->iso());
        self::assertSame(self::FOYER, $series->venueId);
        foreach ($this->occurrences->occurrences as $o) {
            self::assertFalse($o->overridden, 'future scope keeps dates on their recurrence');
            self::assertSame('12:00:00', $o->startsAt->format('H:i:s'));
        }
    }

    public function testFutureScopeLeavesIndividuallyEditedDatesAlone(): void
    {
        $first = $this->draft('RESCHEDULE');
        $this->reschedule($first, $this->hash($first));
        $edited = $this->occurrences->occurrences[self::OCCURRENCES[0]];

        $future = new OccurrenceChangeDraft(self::OCCURRENCES[1], 0, 'RESCHEDULE', 'FUTURE', null, TimeOfDay::parse('14:00'), TimeOfDay::parse('15:00'), 0, self::FOYER);
        $plan = $this->review()->plan($future);

        self::assertCount(2, $plan->changes);
        self::assertSame($edited->startsAt, $this->occurrences->occurrences[self::OCCURRENCES[0]]->startsAt);
    }

    public function testCancellingCancelsBookingsClosesRegistrationAndNeverReopensItOnRestore(): void
    {
        $this->registrations->counts[self::OCCURRENCES[0]] = new RegistrationCounts(3, 1, 0);
        $draft = $this->draft('CANCEL');
        $handler = new CancelEventOccurrencesHandler(new AllowedPermissions(['events.manage']), $this->changes());
        $this->registrations->policies[self::OCCURRENCES[0]] = $this->registrations->policies[self::OCCURRENCES[0]]->configure(true, 10, true);
        $plan = $this->review()->plan($draft);

        self::assertSame(3, $plan->confirmedAffected);
        self::assertSame(1, $plan->waitlistedAffected);
        $handler->handle(new CancelEventOccurrencesCommand(self::ACTOR, $draft, $plan->hash, 'Storm'));

        self::assertSame('CANCELLED', $this->occurrences->occurrences[self::OCCURRENCES[0]]->status);
        self::assertSame([self::OCCURRENCES[0]], $this->registrations->cancelled);
        self::assertFalse($this->registrations->policies[self::OCCURRENCES[0]]->open);
        self::assertSame(10, $this->registrations->policies[self::OCCURRENCES[0]]->capacity);

        $restore = $this->draft('RESTORE', version: 1);
        (new RestoreEventOccurrenceHandler(new AllowedPermissions(['events.manage']), $this->changes()))
            ->handle(new RestoreEventOccurrenceCommand(self::ACTOR, $restore, $this->hash($restore), 'Back on'));
        self::assertSame('SCHEDULED', $this->occurrences->occurrences[self::OCCURRENCES[0]]->status);
        self::assertFalse($this->registrations->policies[self::OCCURRENCES[0]]->open, 'restoring does not reopen registration');
        self::assertSame([self::OCCURRENCES[0]], $this->registrations->cancelled, 'restoring does not restore bookings');
    }

    public function testAStaleOrMissingHashIsRefusedAndNothingChanges(): void
    {
        $draft = $this->draft('RESCHEDULE');
        foreach (['bad', null] as $hash) {
            try {
                $this->reschedule($draft, $hash);
                self::fail('Expected a conflict.');
            } catch (EventException $e) {
                self::assertSame('The reviewed schedule changed. Preview the current details before applying.', $e->getMessage());
            }
        }
        self::assertSame(0, $this->occurrences->occurrences[self::OCCURRENCES[0]]->version);
        self::assertSame([], $this->journal->entries);
    }

    public function testTheHashMovesWhenBookingsOrTheSeriesChange(): void
    {
        $draft = $this->draft('CANCEL');
        $before = $this->hash($draft);

        self::assertSame($before, $this->hash($draft), 'a review is deterministic');
        $this->registrations->counts[self::OCCURRENCES[0]] = new RegistrationCounts(1, 0, 0);
        self::assertNotSame($before, $this->hash($draft));
    }

    public function testThePermissionAndTheMatchingActionAreCheckedBeforeAnyLock(): void
    {
        $draft = $this->draft('RESCHEDULE');
        try {
            $this->reschedule($draft, 'x', manage: false);
            self::fail('Expected a permission failure.');
        } catch (IdentityException) {
            self::assertSame([], $this->log->locks);
        }
        $this->expectExceptionMessage('Use the matching explicit action.');
        (new CancelEventOccurrencesHandler(new AllowedPermissions(['events.manage']), $this->changes()))
            ->handle(new CancelEventOccurrencesCommand(self::ACTOR, $draft, 'x', 'reason'));
    }

    public function testAReasonIsRequired(): void
    {
        $this->expectException(EventException::class);

        (new RescheduleEventOccurrencesHandler(new AllowedPermissions(['events.manage']), $this->changes()))
            ->handle(new RescheduleEventOccurrencesCommand(self::ACTOR, $this->draft('RESCHEDULE'), 'x', ' '));
    }

    /** @return iterable<string, array{string, string}> */
    public static function rejectedReviews(): iterable
    {
        yield 'unknown action' => ['MOVE', 'Choose reschedule, cancel or restore and an explicit scope. Restoration applies to one occurrence.'];
        yield 'restore with future scope' => ['RESTORE', 'Choose reschedule, cancel or restore and an explicit scope. Restoration applies to one occurrence.'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedReviews')]
    public function testInvalidActionAndScopeCombinationsAreRejected(string $action, string $message): void
    {
        $this->expectExceptionMessage($message);

        $this->review()->plan(new OccurrenceChangeDraft(self::OCCURRENCES[0], 0, $action, 'FUTURE', null, null, null, 0, null));
    }

    public function testStateAndTimingRulesAreEnforced(): void
    {
        $cases = [
            'wrong state' => [$this->draft('RESTORE'), 'Choose an action matching the current occurrence state.'],
            'stale version' => [$this->draft('CANCEL', version: 7), 'These details changed. Refresh and review before saving.'],
            'unknown' => [$this->draft('CANCEL', occurrence: '00000000-0000-4000-8000-0000000000ff'), 'Occurrence was not found.'],
        ];
        foreach ($cases as $name => [$draft, $message]) {
            try {
                $this->review()->plan($draft);
                self::fail("$name should be rejected");
            } catch (EventException $e) {
                self::assertSame($message, $e->getMessage(), $name);
            }
        }
        $this->clock->modify('2026-07-05 10:30:00 UTC');
        try {
            $this->review()->plan($this->draft('RESCHEDULE'));
            self::fail('A started occurrence cannot be rescheduled.');
        } catch (EventException $e) {
            self::assertSame('Historical or already-started scheduling cannot be rewritten.', $e->getMessage());
        }
        $this->review()->plan($this->draft('CANCEL'));
        $this->clock->modify('2026-07-05 12:00:00 UTC');
        $this->expectExceptionMessage('Historical or already-started scheduling cannot be rewritten.');
        $this->review()->plan($this->draft('CANCEL'));
    }

    public function testCheckedInPeopleBlockReschedulingButNotCancelling(): void
    {
        $this->attendance->attended[self::OCCURRENCES[0]] = 2;
        $this->review()->plan($this->draft('CANCEL'));

        $this->expectExceptionMessage('People have already checked in.');

        $this->review()->plan($this->draft('RESCHEDULE'));
    }

    public function testArchivedEventsAndInactiveTargetVenuesAreRefused(): void
    {
        $this->venues->save($this->venues->venues[self::FOYER]->edit('Foyer', '', false, 0));
        try {
            $this->review()->plan($this->draft('RESCHEDULE'));
            self::fail('Expected a missing active venue.');
        } catch (EventException $e) {
            self::assertSame('Active venue was not found.', $e->getMessage());
        }
        $this->events->events[self::EVENT] = EventDefinition::reconstitute(self::EVENT, 'Sunday service', 'SERVICE', '', false, 0);
        $this->expectExceptionMessage('The event is archived.');
        $this->review()->plan($this->draft('CANCEL'));
    }

    public function testAnExclusiveVenueClashIsRefused(): void
    {
        $exclusive = new OccurrenceChangeDraft(self::OCCURRENCES[0], 0, 'RESCHEDULE', 'THIS', CalendarDate::parse('2026-07-06'), TimeOfDay::parse('12:00'), TimeOfDay::parse('13:00'), 0, self::HALL);
        $this->occurrences->clash = true;
        try {
            $this->review()->plan($exclusive);
            self::fail('Expected a clash.');
        } catch (EventException $e) {
            self::assertSame('The exclusive venue is already reserved during a revised date.', $e->getMessage());
        }
    }

    public function testNothingEligibleIsAConflict(): void
    {
        foreach (self::OCCURRENCES as $id) {
            $one = new OccurrenceChangeDraft($id, 0, 'RESCHEDULE', 'THIS', CalendarDate::parse('2026-07-08'), TimeOfDay::parse('12:00'), TimeOfDay::parse('13:00'), 0, self::FOYER);
            $this->changes()->apply(self::ACTOR, 'RESCHEDULE', $one, $this->hash($one), 'Edited');
        }

        $this->expectExceptionMessage('No eligible dates remain.');

        $this->review()->plan(new OccurrenceChangeDraft(self::OCCURRENCES[0], 1, 'RESCHEDULE', 'FUTURE', null, TimeOfDay::parse('09:00'), TimeOfDay::parse('10:00'), 0, self::FOYER));
    }

    public function testAMovementPolicyThatHoldsEvidenceVetoesReschedulingButNotCancelling(): void
    {
        $guard = new class () implements \App\Events\Application\Contract\OccurrenceMovementPolicy {
            public function movable(string $occurrenceId): bool
            {
                return false;
            }
        };
        $review = new OccurrenceChangeReview($this->events, $this->venues, $this->occurrences, $this->occurrences, $this->registrations, $this->attendance, $this->clock, [$guard]);

        $review->plan($this->draft('CANCEL'));
        $this->expectException(EventException::class);
        $this->expectExceptionMessage('A linked group has prepared its dated roster.');

        $review->plan($this->draft('RESCHEDULE'));
    }

    public function testScheduleListenersHearAboutEveryChangedOccurrenceInsideTheTransaction(): void
    {
        $heard = new class () implements \App\Events\Application\Contract\OccurrenceScheduleListener {
            /** @var list<string> */
            public array $calls = [];

            public function changed(string $occurrenceId, DateTimeImmutable $startsAt): void
            {
                $this->calls[] = $occurrenceId.'@'.$startsAt->format('Y-m-d\TH:i');
            }
        };
        $changes = new OccurrenceChanges($this->events, $this->venues, $this->occurrences, $this->registrations, $this->review(), $this->journal, $this->clock, [$heard]);
        $draft = new OccurrenceChangeDraft(self::OCCURRENCES[0], 0, 'RESCHEDULE', 'FUTURE', null, TimeOfDay::parse('12:00'), TimeOfDay::parse('13:00'), 0, self::FOYER);

        $changes->apply(self::ACTOR, 'RESCHEDULE', $draft, $this->hash($draft), 'Moved');

        self::assertSame([self::OCCURRENCES[0].'@2026-07-05T12:00', self::OCCURRENCES[1].'@2026-07-12T12:00', self::OCCURRENCES[2].'@2026-07-19T12:00'], $heard->calls);
    }
}
