<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Command\CreateChurchEvent\CreateChurchEventCommand;
use App\Events\Application\Command\CreateChurchEvent\CreateChurchEventHandler;
use App\Events\Application\Command\CreateEventSeries\CreateEventSeriesCommand;
use App\Events\Application\Command\CreateEventSeries\CreateEventSeriesHandler;
use App\Events\Application\Command\UpdateChurchEvent\UpdateChurchEventCommand;
use App\Events\Application\Command\UpdateChurchEvent\UpdateChurchEventHandler;
use App\Events\Application\RequestReplay;
use App\Events\Application\ScheduleDraft;
use App\Events\Application\SchedulePlanner;
use App\Events\Application\ScheduleReview;
use App\Events\Domain\EventDefinition;
use App\Events\Domain\EventException;
use App\Events\Domain\TimeOfDay;
use App\Events\Domain\Venue;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Domain\CalendarDate;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Handler logic in isolation: no database, no transaction, no identity context. */
final class EventHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string VENUE = '00000000-0000-4000-8000-0000000000a1';
    private const string REQUEST = '00000000-0000-4000-8000-0000000000b1';

    private LockLog $log;
    private InMemoryVenueRepository $venues;
    private InMemoryEventRepository $events;
    private InMemoryOccurrenceStore $occurrences;
    private InMemoryRegistrationRepository $policies;
    private RecordingJournal $journal;
    private MockClock $clock;
    private SequentialIds $ids;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->venues = new InMemoryVenueRepository($this->log);
        $this->events = new InMemoryEventRepository($this->log);
        $this->occurrences = new InMemoryOccurrenceStore($this->log);
        $this->policies = new InMemoryRegistrationRepository();
        $this->journal = new RecordingJournal();
        $this->clock = new MockClock('2026-06-01 10:00:00 UTC');
        $this->ids = new SequentialIds();
        $this->venues->insert(Venue::create(self::VENUE, 'Main hall', '1 Church St', true));
    }

    private function draft(int $count = 3, string $venue = self::VENUE): ScheduleDraft
    {
        return new ScheduleDraft($venue, 'UTC', 'WEEKLY', 1, $count, CalendarDate::parse('2026-07-05'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:30'), 0);
    }

    private function review(): ScheduleReview
    {
        return new ScheduleReview($this->venues, $this->occurrences, $this->clock);
    }

    private function create(?string $hash, bool $manage = true, ?ScheduleDraft $draft = null, string $title = 'Sunday service'): string
    {
        return (new CreateChurchEventHandler(
            new AllowedPermissions($manage ? ['events.manage'] : ['events.read']),
            $this->events,
            $this->venues,
            $this->review(),
            new SchedulePlanner($this->occurrences, $this->policies, $this->journal, $this->ids),
            new RequestReplay($this->journal),
            $this->journal,
            $this->clock,
            $this->ids,
        ))->handle(new CreateChurchEventCommand(self::ACTOR, self::REQUEST, $title, 'SERVICE', '', $draft ?? $this->draft(), $hash));
    }

    private function hash(?ScheduleDraft $draft = null): string
    {
        return $this->review()->preview($draft ?? $this->draft())->hash;
    }

    private function addSeries(?string $hash, int $version = 0, bool $manage = true, ?ScheduleDraft $draft = null): string
    {
        $event = array_key_first($this->events->events) ?? '00000000-0000-4000-8000-0000000000ff';

        return (new CreateEventSeriesHandler(
            new AllowedPermissions($manage ? ['events.manage'] : ['events.read']),
            $this->events,
            $this->venues,
            $this->review(),
            new SchedulePlanner($this->occurrences, $this->policies, $this->journal, $this->ids),
            new RequestReplay($this->journal),
            $this->journal,
            $this->clock,
            $this->ids,
        ))->handle(new CreateEventSeriesCommand(self::ACTOR, '00000000-0000-4000-8000-0000000000b2', $event, $version, $draft ?? $this->draft(2), $hash));
    }

    private function update(int $version, bool $active = true, string $reason = 'Rename', bool $manage = true): void
    {
        $event = array_key_first($this->events->events) ?? '00000000-0000-4000-8000-0000000000ff';
        (new UpdateChurchEventHandler(
            new AllowedPermissions($manage ? ['events.manage'] : ['events.read']),
            $this->events,
            $this->occurrences,
            $this->journal,
            $this->clock,
        ))->handle(new UpdateChurchEventCommand(self::ACTOR, $event, $version, 'Renamed', 'MEETING', '', $active, $reason));
    }

    public function testCreatingWritesEventSeriesOccurrencesPoliciesAndAudit(): void
    {
        $id = $this->create($this->hash());

        self::assertSame('Sunday service', $this->events->events[$id]->title);
        self::assertCount(1, $this->events->series);
        self::assertCount(3, $this->occurrences->occurrences);
        self::assertCount(3, $this->policies->policies);
        foreach ($this->policies->policies as $policy) {
            self::assertFalse($policy->open);
            self::assertNull($policy->capacity);
        }
        self::assertContains(self::ACTOR.":event.created:$id", $this->journal->entries);
        self::assertSame(3, \count(array_filter($this->journal->entries, static fn (string $e): bool => str_contains($e, ':occurrence.SCHEDULED:'))));
        self::assertArrayHasKey(self::REQUEST, $this->journal->requests);
    }

    public function testCreatingChecksPermissionBeforeAnything(): void
    {
        try {
            $this->create($this->hash(), manage: false);
            self::fail('Expected a permission failure.');
        } catch (IdentityException) {
            self::assertSame([], $this->events->events);
            self::assertSame([], $this->journal->entries);
            self::assertSame([], $this->log->locks);
        }
    }

    public function testARepeatedRequestReturnsTheOriginalEventWithoutWritingAgain(): void
    {
        $hash = $this->hash();
        $first = $this->create($hash);
        $entries = \count($this->journal->entries);

        self::assertSame($first, $this->create($hash));
        self::assertCount($entries, $this->journal->entries);
        self::assertCount(1, $this->events->events);
    }

    public function testTheSameRequestIdWithDifferentDetailsIsAConflict(): void
    {
        $this->create($this->hash());

        $this->expectException(EventException::class);
        $this->expectExceptionMessage('This request identifier was already used for different details.');

        $this->create($this->hash(), title: 'Other title');
    }

    public function testAStaleOrMissingPreviewHashIsRejectedAndNothingIsWritten(): void
    {
        foreach (['0000', null] as $hash) {
            try {
                $this->create($hash);
                self::fail('Expected a conflict.');
            } catch (EventException $e) {
                self::assertSame('The reviewed schedule changed. Preview the current details before applying.', $e->getMessage());
            }
        }
        self::assertSame([], $this->events->events);
        self::assertSame([], $this->journal->entries);
    }

    public function testPastDatesInactiveVenuesAndClashesAreRejected(): void
    {
        $past = new ScheduleDraft(self::VENUE, 'UTC', 'ONCE', 1, 1, CalendarDate::parse('2026-05-01'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:00'), 0);
        foreach ([[$past, 'New occurrences must start in the future.']] as [$draft, $message]) {
            try {
                $this->review()->preview($draft);
                self::fail('Expected a rejection.');
            } catch (EventException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
        $this->occurrences->clash = true;
        try {
            $this->review()->preview($this->draft());
            self::fail('Expected a clash.');
        } catch (EventException $e) {
            self::assertSame('The exclusive venue is already reserved during a selected date.', $e->getMessage());
        }
        $this->occurrences->clash = false;
        $this->venues->save($this->venues->venues[self::VENUE]->edit('Main hall', '', false, 0));
        $this->expectExceptionMessage('Active venue was not found.');
        $this->review()->preview($this->draft());
    }

    public function testRecurringDatesOverlappingInAnExclusiveVenueAreRejected(): void
    {
        $overlap = new ScheduleDraft(self::VENUE, 'UTC', 'DAILY', 1, 2, CalendarDate::parse('2026-07-05'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:00'), 2);

        $this->expectExceptionMessage('The recurring dates overlap in the exclusive venue.');

        $this->review()->preview($overlap);
    }

    public function testAddingASeriesLocksTheEventThenTheVenueAndTouchesTheEvent(): void
    {
        $event = $this->create($this->hash());
        $this->log->locks = [];

        $series = $this->addSeries($this->hash($this->draft(2)));

        self::assertSame(["event:$event", 'venue:'.self::VENUE], $this->log->locks);
        self::assertArrayHasKey($series, $this->events->series);
        self::assertSame(1, $this->events->events[$event]->version);
        self::assertCount(5, $this->occurrences->occurrences);
    }

    public function testAddingASeriesRejectsAStaleEventVersionAndAnArchivedEvent(): void
    {
        $event = $this->create($this->hash());
        try {
            $this->addSeries($this->hash($this->draft(2)), version: 4);
            self::fail('Expected a stale version.');
        } catch (EventException $e) {
            self::assertSame('These details changed. Refresh and review before saving.', $e->getMessage());
        }
        $this->events->events[$event] = EventDefinition::reconstitute($event, 'Sunday service', 'SERVICE', '', false, 0);
        $this->expectExceptionMessage('Active event was not found.');
        $this->addSeries($this->hash($this->draft(2)));
    }

    public function testAddingASeriesChecksPermissionFirst(): void
    {
        $this->expectException(IdentityException::class);

        $this->addSeries(null, manage: false);
    }

    public function testUpdatingRequiresAReasonAndTheCurrentVersion(): void
    {
        $event = $this->create($this->hash());
        try {
            $this->update(0, reason: ' ');
            self::fail('Expected a reason failure.');
        } catch (EventException) {
            self::assertSame(0, $this->events->events[$event]->version);
        }
        try {
            $this->update(3);
            self::fail('Expected a stale version.');
        } catch (EventException) {
            self::assertNotContains(self::ACTOR.":event.updated:$event", $this->journal->entries);
        }
        $this->update(0);

        self::assertSame('Renamed', $this->events->events[$event]->title);
        self::assertContains(self::ACTOR.":event.updated:$event", $this->journal->entries);
    }

    public function testAnEventWithFutureScheduledOccurrencesCannotBeArchived(): void
    {
        $this->create($this->hash());
        $this->occurrences->future = true;

        $this->expectExceptionMessage('Cancel the remaining scheduled occurrences before archiving this event.');

        $this->update(0, active: false);
    }

    public function testUpdatingChecksPermissionFirstAndMissingEventsAreNotFound(): void
    {
        try {
            $this->update(0, manage: false);
            self::fail('Expected a permission failure.');
        } catch (IdentityException) {
            self::assertSame([], $this->log->locks);
        }
        $this->expectExceptionMessage('Event was not found.');
        $this->update(0);
    }
}
