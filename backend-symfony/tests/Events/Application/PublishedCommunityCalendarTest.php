<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Command\CreateChurchEvent\CreateChurchEventHandler;
use App\Events\Application\Contract\CalendarFailure;
use App\Events\Application\Contract\CommunitySchedule;
use App\Events\Application\RequestReplay;
use App\Events\Application\SchedulePlanner;
use App\Events\Application\ScheduleReview;
use App\Events\Domain\TimeOfDay;
use App\Events\Domain\Venue;
use App\Events\Infrastructure\PublishedCommunityCalendar;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Domain\CalendarDate;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class PublishedCommunityCalendarTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string HALL = '00000000-0000-4000-8000-0000000000a1';

    private InMemoryOccurrenceStore $occurrences;
    private InMemoryVenueRepository $venues;
    private RecordingJournal $journal;
    private LockLog $log;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->occurrences = new InMemoryOccurrenceStore($this->log);
        $this->venues = new InMemoryVenueRepository($this->log);
        $this->venues->insert(Venue::create(self::HALL, 'Main hall', '', true));
        $this->journal = new RecordingJournal();
    }

    private function calendar(bool $manage = true): PublishedCommunityCalendar
    {
        $clock = new MockClock('2026-06-01 10:00:00 UTC');
        $ids = new SequentialIds();
        $permissions = new AllowedPermissions($manage ? ['events.manage'] : []);
        $events = new InMemoryEventRepository($this->log);
        $review = new ScheduleReview($this->venues, $this->occurrences, $clock);

        return new PublishedCommunityCalendar(
            $review,
            new CreateChurchEventHandler($permissions, $events, $this->venues, $review, new SchedulePlanner($this->occurrences, new InMemoryRegistrationRepository(), $this->journal, $ids), new RequestReplay($this->journal), $this->journal, $clock, $ids),
            $this->occurrences,
            $this->occurrences,
            $permissions,
        );
    }

    private function schedule(string $venue = self::HALL, int $count = 3): CommunitySchedule
    {
        return new CommunitySchedule($venue, 'UTC', 'WEEKLY', 1, $count, CalendarDate::parse('2026-07-05'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:00'), 0);
    }

    public function testAPreviewListsTheDatesWithoutIdsAndWritesNothing(): void
    {
        $preview = $this->calendar()->preview($this->schedule());

        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $preview->hash);
        self::assertCount(3, $preview->dates);
        self::assertNull($preview->dates[0]->id);
        self::assertSame('Main hall', $preview->dates[0]->venue);
        self::assertSame('SCHEDULED', $preview->dates[0]->status);
        self::assertSame(0, $preview->dates[0]->revision);
        self::assertSame([], $this->occurrences->occurrences);
    }

    public function testCreatingSchedulesAMeetingEventAndReturnsItsDates(): void
    {
        $calendar = $this->calendar();
        $hash = $calendar->preview($this->schedule())->hash;

        $dates = $calendar->create(self::ACTOR, '00000000-0000-4000-8000-0000000000b1', 'Home group', $this->schedule(), $hash);

        self::assertCount(3, $dates);
        self::assertNotNull($dates[0]->id);
        self::assertCount(3, $this->occurrences->occurrences);
        self::assertSame($dates[1]->id, $calendar->get((string) $dates[1]->id)?->id);
        self::assertCount(2, $calendar->dates([(string) $dates[0]->id, (string) $dates[2]->id, '00000000-0000-4000-8000-0000000000ff']));
        self::assertNull($calendar->get('00000000-0000-4000-8000-0000000000ff'));
        self::assertSame((string) $dates[0]->id, $calendar->lock((string) $dates[0]->id)?->id);
        self::assertContains('occurrence:'.$dates[0]->id, $this->log->locks);
    }

    /** @return iterable<string, array{string, int}> */
    public static function refusals(): iterable
    {
        yield 'unknown venue' => ['00000000-0000-4000-8000-0000000000ee', 404];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusals')]
    public function testSchedulingRefusalsCarryTheHttpStatusOfTheEventsApi(string $venue, int $status): void
    {
        try {
            $this->calendar()->preview($this->schedule($venue));
            self::fail('Expected a failure.');
        } catch (CalendarFailure $e) {
            self::assertSame($status, $e->status);
        }
    }

    public function testAStaleHashIsAConflictAndInvalidInputIsA400(): void
    {
        $calendar = $this->calendar();
        try {
            $calendar->create(self::ACTOR, '00000000-0000-4000-8000-0000000000b2', 'Home group', $this->schedule(), 'stale');
            self::fail('Expected a conflict.');
        } catch (CalendarFailure $e) {
            self::assertSame(409, $e->status);
        }
        try {
            $calendar->preview($this->schedule(count: 0));
            self::fail('Expected an invalid request.');
        } catch (CalendarFailure $e) {
            self::assertSame(400, $e->status);
        }
        self::assertSame([], $this->occurrences->occurrences);
    }

    public function testSchedulingNeedsTheEventsManagePermission(): void
    {
        $this->expectException(IdentityException::class);

        $this->calendar(manage: false)->preview($this->schedule());
    }
}
