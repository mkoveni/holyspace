<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Command\CancelSessionSeries\CancelSessionSeriesCommand;
use App\ChildrenMinistry\Sessions\Application\Command\CancelSessionSeries\CancelSessionSeriesHandler;
use App\ChildrenMinistry\Sessions\Application\Command\CreateSessionSeries\CreateSessionSeriesCommand;
use App\ChildrenMinistry\Sessions\Application\Command\CreateSessionSeries\CreateSessionSeriesHandler;
use App\ChildrenMinistry\Sessions\Application\Query\PreviewSessionSeries\PreviewSessionSeriesHandler;
use App\ChildrenMinistry\Sessions\Application\Query\PreviewSessionSeries\PreviewSessionSeriesQuery;
use App\ChildrenMinistry\Sessions\Application\SessionSeriesDraft;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Sessions\Domain\WeeklyRecurrence;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class SeriesHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string REQUEST = '88888888-8888-4888-8888-888888888888';
    private const string LEADER = '10000000-0000-4000-8000-000000000001';
    private const string HELPER = '10000000-0000-4000-8000-000000000002';

    private CallLog $log;
    private MockClock $clock;
    private SessionFixture $fixture;
    private SeriesWorld $world;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->clock = new MockClock('2026-06-01 08:00:00 UTC');
        $this->fixture = new SessionFixture($this->log);
        $this->fixture->serve(self::LEADER, StaffRole::Leader, false);
        $this->fixture->serve(self::HELPER, StaffRole::Assistant, false);
        $this->world = new SeriesWorld($this->fixture, $this->log);
    }

    /** @param list<string> $skipped */
    private function draft(int $occurrences = 3, array $skipped = [], string $first = '2026-06-14', int $version = 0, string $name = 'Term 3'): SessionSeriesDraft
    {
        return new SessionSeriesDraft($name, SessionFixture::SESSION, $version, new WeeklyRecurrence(CalendarDate::parse($first), '09:00', '10:00', 1, $occurrences, array_map(CalendarDate::parse(...), $skipped)));
    }

    /** @param list<string> $permissions */
    private function create(array $permissions = ['children.manage']): CreateSessionSeriesHandler
    {
        return new CreateSessionSeriesHandler(new AllowedPermissions($permissions), $this->world, $this->fixture, $this->world->planning($this->clock), new RecordingChildrenAudit($this->log), $this->clock, new SequentialIds());
    }

    /** @param list<string> $permissions */
    private function cancel(array $permissions = ['children.manage']): CancelSessionSeriesHandler
    {
        return new CancelSessionSeriesHandler(new AllowedPermissions($permissions), $this->world, $this->fixture, $this->fixture, new RecordingChildrenAudit($this->log), $this->clock);
    }

    private function make(SessionSeriesDraft $draft, string $request = self::REQUEST): string
    {
        return $this->create()->handle(new CreateSessionSeriesCommand(self::ACTOR, $request, $draft));
    }

    public function testCreatingCopiesTheTeamAbsentAndAuditsEachSessionThenTheSeries(): void
    {
        $id = $this->make($this->draft(3, ['2026-06-21']));

        self::assertCount(2, $this->world->links[$id]);
        $created = array_values(array_filter($this->fixture->sessions, static fn (MinistrySession $s): bool => SessionFixture::SESSION !== $s->id));
        self::assertCount(2, $created);
        foreach ($created as $session) {
            self::assertSame(SessionStatus::Planned, $session->status);
        }
        // The shared fixture keeps one staff row per worker, so assert the writes: both workers, absent, for each session.
        self::assertSame(4, count(array_filter($this->log->entries, static fn (string $e): bool => 'save:staff:LEADER:absent' === $e || 'save:staff:ASSISTANT:absent' === $e)));
        self::assertNotContains('save:staff:LEADER:present', $this->log->entries);
        self::assertSame('2026-06-14T07:00:00Z', $created[0]->startsAt->format('Y-m-d\TH:i:s\Z'));
        self::assertSame(['audit:session.created', 'audit:session.created', 'audit:session.series.created'], array_values(array_filter($this->log->entries, static fn (string $e): bool => str_starts_with($e, 'audit:'))));
    }

    public function testSchedulingIsLockedBeforeTheSourceSession(): void
    {
        $this->make($this->draft(1));

        $locks = array_values(array_filter($this->log->entries, static fn (string $e): bool => str_starts_with($e, 'lock:') || str_starts_with($e, 'forUpdate:')));
        self::assertSame(['lock:scheduling', 'forUpdate:session'], $locks);
    }

    public function testARetryWithTheSameRequestReturnsTheSameSeriesWithoutWriting(): void
    {
        $id = $this->make($this->draft());
        $writes = count($this->log->entries);
        $sessions = count($this->fixture->sessions);

        self::assertSame($id, $this->make($this->draft()));
        self::assertCount($sessions, $this->fixture->sessions);
        self::assertSame($writes + 1, count($this->log->entries));
    }

    public function testTheSameRequestWithAChangedPlanIsAConflict(): void
    {
        $this->make($this->draft());

        $this->expectException(ChildrenException::class);
        $this->expectExceptionMessage('already used for a different schedule');
        $this->make($this->draft(2));
    }

    public function testAClashRejectsTheWholeSeriesBeforeAnythingIsWritten(): void
    {
        $this->fixture->sessions['clash'] = MinistrySession::plan('clash', 'other-class', SessionFixture::ROOM, new DateTimeImmutable('2026-06-21T07:30:00Z'), new DateTimeImmutable('2026-06-21T08:30:00Z'), 5);
        $before = count($this->fixture->sessions);

        try {
            $this->make($this->draft());
            self::fail('Expected a conflict.');
        } catch (ChildrenException $conflict) {
            self::assertStringContainsString('no longer available', $conflict->getMessage());
        }
        self::assertCount($before, $this->fixture->sessions);
        self::assertSame([], $this->world->series);
        self::assertNotContains('audit:session.series.created', $this->log->entries);
    }

    public function testPastStartsAreBlockingConflicts(): void
    {
        $this->clock->modify('2026-06-14 08:00:00 UTC');

        $this->expectException(ChildrenException::class);
        $this->make($this->draft(1));
    }

    public function testCreatingRequiresManageBeforeAnyLock(): void
    {
        try {
            $this->create(['children.read'])->handle(new CreateSessionSeriesCommand(self::ACTOR, self::REQUEST, $this->draft()));
            self::fail('Expected a denial.');
        } catch (IdentityException) {
            self::assertSame([], $this->log->entries);
        }
    }

    public function testAStaleSourceVersionAndAMissingSourceAreRejected(): void
    {
        try {
            $this->make($this->draft(1, [], '2026-06-14', 5));
            self::fail('Expected a stale version.');
        } catch (ChildrenException $stale) {
            self::assertStringContainsString('changed', $stale->getMessage());
        }
        $this->expectException(ChildrenException::class);
        $this->create()->handle(new CreateSessionSeriesCommand(self::ACTOR, self::REQUEST, new SessionSeriesDraft('x', '77777777-7777-4777-8777-777777777777', 0, new WeeklyRecurrence(CalendarDate::parse('2026-06-14'), '09:00', '10:00', 1, 1))));
    }

    public function testPreviewWarnsAboutIneligibleTeamMembersButDoesNotBlock(): void
    {
        $this->fixture->workers[self::HELPER] = \App\ChildrenMinistry\Sessions\Domain\Worker::reconstitute(self::HELPER, 'acct-'.self::HELPER, new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2026-06-10T00:00:00Z'), new DateTimeImmutable('2026-06-10T00:00:00Z'), 'Checked', 'v', null, 0);
        $handler = new PreviewSessionSeriesHandler(new AllowedPermissions(['children.manage']), $this->world->planning($this->clock));

        $preview = $handler->handle(new PreviewSessionSeriesQuery($this->draft(2)));

        self::assertSame('Africa/Johannesburg', $preview->timeZone);
        self::assertFalse($preview->hasConflicts());
        foreach ($preview->occurrences as $occurrence) {
            self::assertSame(1, count(array_keys($occurrence->staffingWarnings, 'Worker 10: review identity, account or screening/training before this session.', true)));
        }
        self::assertSame([], $this->world->series);
    }

    public function testPreviewReportsReducedCapacityAndMissingLeader(): void
    {
        $this->fixture->staff = array_values(array_filter($this->fixture->staff, static fn ($s): bool => StaffRole::Leader !== $s->role));
        $handler = new PreviewSessionSeriesHandler(new AllowedPermissions(['children.manage']), $this->world->planning($this->clock));

        $warnings = $handler->handle(new PreviewSessionSeriesQuery($this->draft(1)))->occurrences[0]->staffingWarnings;

        self::assertContains('The copied team currently supports up to 0 of 10 planned seats once present. Review staffing before opening.', $warnings);
        self::assertContains('Assign a leader before opening this session.', $warnings);
    }

    public function testPreviewMarksExcludedDatesWithoutConflictChecks(): void
    {
        $handler = new PreviewSessionSeriesHandler(new AllowedPermissions(['children.manage']), $this->world->planning($this->clock));

        $occurrences = $handler->handle(new PreviewSessionSeriesQuery($this->draft(2, ['2026-06-14'])))->occurrences;

        self::assertTrue($occurrences[0]->slot->excluded);
        self::assertSame([], $occurrences[0]->conflicts);
    }

    public function testPreviewNeedsManage(): void
    {
        $this->expectException(IdentityException::class);
        new PreviewSessionSeriesHandler(new AllowedPermissions(['children.read']), $this->world->planning($this->clock))->handle(new PreviewSessionSeriesQuery($this->draft()));
    }

    public function testCancellingStopsTheSeriesAndCancelsOnlyFuturePlannedSessions(): void
    {
        $id = $this->make($this->draft(3));
        [$first, $second, $third] = $this->world->sessionIds($id);
        $this->fixture->sessions[$second] = $this->fixture->sessions[$second]->withStatus(SessionStatus::Open, 0);
        $this->clock->modify('2026-06-14 05:00:00 UTC');
        $before = count($this->log->entries);

        $this->cancel()->handle(new CancelSessionSeriesCommand(self::ACTOR, $id, 0, 'Term change'));

        self::assertSame(SessionStatus::Cancelled, $this->fixture->sessions[$first]->status);
        self::assertSame(SessionStatus::Open, $this->fixture->sessions[$second]->status);
        self::assertSame(SessionStatus::Cancelled, $this->fixture->sessions[$third]->status);
        self::assertTrue($this->world->series[$id]->stopped);
        self::assertSame(1, $this->world->series[$id]->version);
        self::assertSame(['audit:session.status.changed', 'audit:session.status.changed', 'audit:session.series.cancelled'], array_values(array_filter(array_slice($this->log->entries, $before), static fn (string $e): bool => str_starts_with($e, 'audit:'))));
    }

    public function testCancellingLocksTheSchedulingGuardThenTheSeriesThenSessionsInUuidOrder(): void
    {
        $id = $this->make($this->draft(3));
        $before = count($this->log->entries);

        $this->cancel()->handle(new CancelSessionSeriesCommand(self::ACTOR, $id, 0, 'Term change'));

        self::assertSame(['lock:scheduling', 'forUpdate:series', 'forUpdate:session', 'forUpdate:session', 'forUpdate:session'], array_values(array_filter(array_slice($this->log->entries, $before), static fn (string $e): bool => str_starts_with($e, 'lock:') || str_starts_with($e, 'forUpdate:'))));
    }

    public function testCancellingRefusesWhileChildrenAreStillInAPlannedSession(): void
    {
        $id = $this->make($this->draft(1));
        $this->fixture->occupancy = 1;

        $this->expectException(ChildrenException::class);
        $this->expectExceptionMessage('Collect all children');
        $this->cancel()->handle(new CancelSessionSeriesCommand(self::ACTOR, $id, 0, 'Term change'));
    }

    public function testCancellingTwiceAndWithAStaleVersionAreConflicts(): void
    {
        $id = $this->make($this->draft(1));
        try {
            $this->cancel()->handle(new CancelSessionSeriesCommand(self::ACTOR, $id, 9, 'Term change'));
            self::fail('Expected a stale version.');
        } catch (ChildrenException $stale) {
            self::assertStringContainsString('changed', $stale->getMessage());
        }
        $this->cancel()->handle(new CancelSessionSeriesCommand(self::ACTOR, $id, 0, 'Term change'));
        $this->expectExceptionMessage('already been cancelled');
        $this->cancel()->handle(new CancelSessionSeriesCommand(self::ACTOR, $id, 1, 'Again'));
    }

    public function testCancellingNeedsManageAndAReasonAndAKnownSeries(): void
    {
        try {
            $this->cancel(['children.read'])->handle(new CancelSessionSeriesCommand(self::ACTOR, 'x', 0, 'r'));
            self::fail('Expected a denial.');
        } catch (IdentityException) {
            self::assertSame([], $this->log->entries);
        }
        try {
            $this->cancel()->handle(new CancelSessionSeriesCommand(self::ACTOR, '66666666-6666-4666-8666-666666666666', 0, 'r'));
            self::fail('Expected not found.');
        } catch (ChildrenException $missing) {
            self::assertStringContainsString('Series', $missing->getMessage());
        }
        $id = $this->make($this->draft(1));
        $this->expectException(ChildrenException::class);
        $this->cancel()->handle(new CancelSessionSeriesCommand(self::ACTOR, $id, 0, '  '));
    }
}
