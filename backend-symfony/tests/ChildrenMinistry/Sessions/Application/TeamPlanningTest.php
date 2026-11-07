<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Command\ApplyTeamPlan\ApplyTeamPlanCommand;
use App\ChildrenMinistry\Sessions\Application\Command\ApplyTeamPlan\ApplyTeamPlanHandler;
use App\ChildrenMinistry\Sessions\Application\Contract\TeamPlanningView;
use App\ChildrenMinistry\Sessions\Application\Query\GetTeamPlanning\GetTeamPlanningHandler;
use App\ChildrenMinistry\Sessions\Application\Query\GetTeamPlanning\GetTeamPlanningQuery;
use App\ChildrenMinistry\Sessions\Application\Query\ListPlanningWorkers\ListPlanningWorkersHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListPlanningWorkers\ListPlanningWorkersQuery;
use App\ChildrenMinistry\Sessions\Application\Query\PreviewTeamPlan\PreviewTeamPlanHandler;
use App\ChildrenMinistry\Sessions\Application\Query\PreviewTeamPlan\PreviewTeamPlanQuery;
use App\ChildrenMinistry\Sessions\Application\TeamPlanDraft;
use App\ChildrenMinistry\Sessions\Application\TeamPlanPreview;
use App\ChildrenMinistry\Sessions\Application\TeamPlanTarget;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class TeamPlanningTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string LEADER = '10000000-0000-4000-8000-000000000001';
    private const string HELPER = '10000000-0000-4000-8000-000000000002';
    private const string SECOND = 'e0000000-0000-4000-8000-000000000002';
    private const string THIRD = 'e0000000-0000-4000-8000-000000000003';

    private CallLog $log;
    private MockClock $clock;
    private SessionFixture $fixture;
    private PlanningWorld $world;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->clock = new MockClock('2026-06-01 08:00:00 UTC');
        $this->fixture = new SessionFixture($this->log);
        $this->world = new PlanningWorld($this->fixture, $this->log);
        // Registered, cleared workers who are not yet on the fixture's session.
        $this->register(self::LEADER);
        $this->register(self::HELPER);
    }

    private function register(string $id): void
    {
        $this->fixture->serve($id, StaffRole::Assistant, false);
        $this->fixture->staff = array_values(array_filter($this->fixture->staff, static fn ($member): bool => $member->workerId !== $id));
    }

    private function assign(string $worker, string $session = SessionFixture::SESSION, StaffRole $role = StaffRole::Leader, bool $present = false): void
    {
        $this->fixture->staff[] = new SessionStaff($session, $worker, $role, $present);
    }

    /** @param list<array{string, int}> $targets */
    private function draft(array $targets = [[SessionFixture::SESSION, 0]], string $worker = self::LEADER, ?string $replace = null, StaffRole $role = StaffRole::Leader): TeamPlanDraft
    {
        return new TeamPlanDraft(array_map(static fn (array $t): TeamPlanTarget => new TeamPlanTarget($t[0], $t[1]), $targets), $worker, $replace, $role);
    }

    /** @param list<string> $permissions */
    private function preview(TeamPlanDraft $draft, array $permissions = ['children.manage']): TeamPlanPreview
    {
        return new PreviewTeamPlanHandler(new AllowedPermissions($permissions), $this->world->planning($this->clock))->handle(new PreviewTeamPlanQuery($draft));
    }

    private function list(string $state = 'ALL', string $search = '', string $from = '2026-06-01', string $until = '2026-06-30', int $limit = 25, int $offset = 0): TeamPlanningView
    {
        return new GetTeamPlanningHandler(new AllowedPermissions(['children.manage']), $this->world->planning($this->clock))
            ->handle(new GetTeamPlanningQuery(CalendarDate::parse($from), CalendarDate::parse($until), null, null, $search, $state, $limit, $offset));
    }

    /** @param list<string> $permissions */
    private function apply(TeamPlanDraft $draft, string $reason = 'Sunday team agreed', array $permissions = ['children.manage'], ?SessionFixture $sessions = null): void
    {
        $sessions ??= $this->fixture;
        new ApplyTeamPlanHandler(new AllowedPermissions($permissions), $this->world->planning($this->clock), new IdRecordingSessions($sessions, $this->log), $this->world, new RecordingChildrenAudit($this->log), $this->clock)
            ->handle(new ApplyTeamPlanCommand(self::ACTOR, $draft, $reason));
    }

    public function testAFreshSessionWithoutATeamNeedsAttention(): void
    {
        $view = $this->list();

        self::assertSame(1, $view->total);
        self::assertSame([1, 0, 1, 1], [$view->summary->sessions, $view->summary->ready, $view->summary->attention, $view->summary->missingLeaders]);
        self::assertSame('Africa/Johannesburg', $view->timeZone);
    }

    public function testAReadyTeamNeedsAQualifiedLeaderAndEnoughSeats(): void
    {
        $this->world->addSession(self::SECOND, '2026-06-14T09:00:00Z', '2026-06-14T11:00:00Z', seats: 2);
        $this->assign(self::LEADER, self::SECOND);
        $this->assign(self::HELPER, self::SECOND, StaffRole::Assistant);

        $rows = $this->list()->sessions;
        $ready = array_values(array_filter($rows, static fn ($row): bool => self::SECOND === $row->session->id))[0];

        self::assertTrue($ready->ready);
        self::assertSame([1, 2, 2, true, true], [$ready->requiredStaff, $ready->qualifiedStaff, $ready->plannedCapacity, $ready->qualifiedLeader, $ready->editable]);
        self::assertSame([], $ready->issues);
        self::assertSame(['Worker 10', 'Worker 10'], array_map(static fn ($member): string => $member->name, $ready->team));
    }

    public function testShortCapacityAndMissingLeadersAreReportedWithTheStaffIssues(): void
    {
        $this->assign(self::HELPER, SessionFixture::SESSION, StaffRole::Assistant);

        $row = $this->list()->sessions[0];

        self::assertFalse($row->ready);
        self::assertSame(5, $row->requiredStaff);
        self::assertContains('A qualified, available leader is needed.', $row->issues);
        self::assertContains('The planned team supports 0 of 10 seats.', $row->issues);
    }

    public function testUnqualifiedStaffCountForNothingAndExplainWhy(): void
    {
        $this->fixture->workers[self::LEADER] = Worker::reconstitute(self::LEADER, 'acct-'.self::LEADER, new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2026-06-05T00:00:00Z'), new DateTimeImmutable('2026-06-05T00:00:00Z'), 'Checked', 'v', null, 0);
        $this->fixture->accounts['acct-'.self::HELPER] = false;
        $this->fixture->people[self::HELPER] = ['Gone person', '2015-01-01'];
        $this->assign(self::LEADER);
        $this->assign(self::HELPER, SessionFixture::SESSION, StaffRole::Assistant);

        $row = $this->list()->sessions[0];

        self::assertSame(0, $row->qualifiedStaff);
        self::assertFalse($row->qualifiedLeader);
        self::assertContains('Worker 10: Screening or training does not cover this session, or clearance is revoked.', $row->issues);
        self::assertContains("Gone person: Review the person's availability and adult identity.", $row->issues);
        self::assertContains('Gone person: The linked worker account is unavailable.', $row->issues);
    }

    public function testDoubleBookedStaffAreFlaggedOnBothSessionsAndLeaveTheCapacity(): void
    {
        $this->world->addSession(self::SECOND, '2026-06-07T10:00:00Z', '2026-06-07T12:00:00Z');
        $this->assign(self::LEADER, SessionFixture::SESSION);
        $this->assign(self::LEADER, self::SECOND);

        $view = $this->list();

        self::assertSame(2, $view->summary->clashes);
        foreach ($view->sessions as $row) {
            self::assertTrue($row->team[0]->clash);
            self::assertSame(0, $row->qualifiedStaff);
            self::assertContains('Worker 10: Assigned to an overlapping session.', $row->issues);
        }
    }

    public function testAdjacentSessionsAndTerminalOnesDoNotClash(): void
    {
        $this->world->addSession(self::SECOND, '2026-06-07T11:00:00Z', '2026-06-07T12:00:00Z');
        $this->world->addSession(self::THIRD, '2026-06-07T09:30:00Z', '2026-06-07T10:00:00Z', SessionStatus::Cancelled);
        foreach ([SessionFixture::SESSION, self::SECOND, self::THIRD] as $session) {
            $this->assign(self::LEADER, $session);
        }

        self::assertSame(0, $this->list()->summary->clashes);
    }

    public function testFiltersByReadinessSearchAndPage(): void
    {
        $this->world->addSession(self::SECOND, '2026-06-14T09:00:00Z', '2026-06-14T11:00:00Z', seats: 2);
        $this->assign(self::LEADER, self::SECOND);

        $ready = $this->list('READY');
        self::assertSame([1, 2, 1, 1], [$ready->total, $ready->summary->sessions, $ready->summary->ready, $ready->summary->attention]);
        self::assertSame(self::SECOND, $ready->sessions[0]->session->id);
        self::assertSame(1, $this->list('ATTENTION')->total);
        self::assertSame(1, $this->list('ALL', 'worker 10')->total, 'search matches staff names, case-insensitively');
        self::assertSame(2, $this->list('ALL', 'juniors')->total);
        self::assertSame(0, $this->list('ALL', 'nothing like it')->total);
        $page = $this->list('ALL', '', '2026-06-01', '2026-06-30', 1, 1);
        self::assertSame([2, 1, self::SECOND], [$page->total, count($page->sessions), $page->sessions[0]->session->id]);
    }

    public function testStartedAndTerminalSessionsAreNotListedAndTheWindowIsInclusive(): void
    {
        $this->world->addSession(self::SECOND, '2026-06-01T07:00:00Z', '2026-06-01T08:30:00Z');
        $this->world->addSession(self::THIRD, '2026-06-30T20:00:00Z', '2026-06-30T21:00:00Z');

        self::assertSame(2, $this->list('ALL', '', '2026-06-02', '2026-06-30')->total, 'the fixture session and a late evening session on the last local day');
        self::assertSame(1, $this->list('ALL', '', '2026-06-02', '2026-06-29')->total);
    }

    public function testRangeAndStateAreValidated(): void
    {
        foreach ([['ALL', '2026-06-30', '2026-06-01'], ['ALL', '2026-06-01', '2026-09-01'], ['WHATEVER', '2026-06-01', '2026-06-30']] as [$state, $from, $until]) {
            try {
                $this->list($state, '', $from, $until);
                self::fail('Expected a rejection.');
            } catch (ChildrenException $invalid) {
                self::assertNotSame('', $invalid->getMessage());
            }
        }
        self::assertSame(1, $this->list('ALL', '', '2026-06-01', '2026-08-29')->total, '90 days inclusive is allowed');
        $this->expectException(ChildrenException::class);
        $this->list('ALL', '', '2026-06-01', '2026-06-30', 0);
    }

    public function testMoreThanAThousandMatchingSessionsIsRefused(): void
    {
        for ($i = 0; $i < 1001; ++$i) {
            $this->world->addSession(sprintf('f0000000-0000-4000-8000-%012d', $i), '2026-06-20T09:00:00Z', '2026-06-20T10:00:00Z');
        }

        $this->expectExceptionMessage('More than 1,000 sessions match');
        $this->list();
    }

    public function testPlanningWorkersAreNamedWithoutEvidence(): void
    {
        $page = new ListPlanningWorkersHandler(new AllowedPermissions(['children.manage']), $this->world->planning($this->clock))->handle(new ListPlanningWorkersQuery('', 25, 0));

        self::assertSame(2, $page->total);
        self::assertSame(['Worker 10', false], [$page->workers[0]->name, $page->workers[0]->revoked]);
        self::assertSame(['id', 'name', 'screenedUntil', 'trainedUntil', 'revoked'], array_keys(get_object_vars($page->workers[0])));
    }

    public function testEverythingNeedsManage(): void
    {
        $planning = $this->world->planning($this->clock);
        foreach ([
            fn () => new GetTeamPlanningHandler(new AllowedPermissions(['children.read']), $planning)->handle(new GetTeamPlanningQuery(CalendarDate::parse('2026-06-01'), CalendarDate::parse('2026-06-30'), null, null, '', 'ALL', 25, 0)),
            fn () => new ListPlanningWorkersHandler(new AllowedPermissions(['children.read']), $planning)->handle(new ListPlanningWorkersQuery('', 25, 0)),
            fn () => $this->preview($this->draft(), ['children.read']),
            fn () => $this->apply($this->draft(), 'Reason', ['children.read']),
        ] as $call) {
            try {
                $call();
                self::fail('Expected a denial.');
            } catch (IdentityException) {
                self::assertSame([], $this->log->entries);
            }
        }
    }

    public function testPreviewProjectsAnAbsentLeaderAndWritesNothing(): void
    {
        $preview = $this->preview($this->draft());

        self::assertSame([], $preview->blockers);
        $member = $preview->sessions[0]->team[0];
        self::assertSame([self::LEADER, 'LEADER', false, true, false], [$member->workerId, $member->role, $member->present, $member->qualified, $member->clash]);
        self::assertSame(2, $preview->sessions[0]->plannedCapacity);
        self::assertSame([], $this->fixture->staff);
        self::assertSame([], $this->log->entries);
    }

    public function testPreviewBlocksStaleStartedAndNonPlannedSessions(): void
    {
        $this->world->addSession(self::SECOND, '2026-05-30T09:00:00Z', '2026-05-30T11:00:00Z');
        $this->fixture->sessions[self::THIRD] = MinistrySession::reconstitute(self::THIRD, SessionFixture::CLASS_ID, SessionFixture::ROOM, new DateTimeImmutable('2026-06-20T09:00:00Z'), new DateTimeImmutable('2026-06-20T10:00:00Z'), 5, SessionStatus::Open, 3);

        self::assertSame(['A selected session changed. Reload the planning workspace.'], $this->preview($this->draft([[SessionFixture::SESSION, 4]]))->blockers);
        self::assertSame(['Only future planned sessions can be changed in bulk.'], $this->preview($this->draft([[self::SECOND, 0]]))->blockers);
        self::assertSame(['Only future planned sessions can be changed in bulk.'], $this->preview($this->draft([[self::THIRD, 3]]))->blockers);
    }

    public function testReplacementNeedsMembershipAndConfirmedPresenceIsNeverRewritten(): void
    {
        self::assertSame(['The person to replace is not assigned to every selected session.'], $this->preview($this->draft([[SessionFixture::SESSION, 0]], self::HELPER, self::LEADER))->blockers);

        $this->assign(self::LEADER, SessionFixture::SESSION, StaffRole::Leader, true);
        self::assertSame(['Confirmed presence must be managed on the individual session.'], $this->preview($this->draft([[SessionFixture::SESSION, 0]], self::HELPER, self::LEADER))->blockers);
        self::assertSame(['Confirmed presence must be managed on the individual session.'], $this->preview($this->draft([[SessionFixture::SESSION, 0]], self::LEADER))->blockers);
    }

    public function testPreviewBlocksAnOverlappingAssignmentOfTheChosenWorker(): void
    {
        $this->world->addSession(self::SECOND, '2026-06-07T10:00:00Z', '2026-06-07T12:00:00Z');
        $this->assign(self::LEADER, self::SECOND);

        $blockers = $this->preview($this->draft())->blockers;

        self::assertSame(['The selected worker has an overlapping assignment. Choose different sessions or resolve the clash first.'], $blockers);
    }

    public function testPlanningTwoOverlappingSessionsTogetherClashesWithItself(): void
    {
        $this->world->addSession(self::SECOND, '2026-06-07T10:00:00Z', '2026-06-07T12:00:00Z');

        self::assertCount(1, $this->preview($this->draft([[SessionFixture::SESSION, 0], [self::SECOND, 0]]))->blockers);
    }

    public function testPreviewRejectsMalformedDraftsAndUnknownTargets(): void
    {
        foreach ([[], array_map(static fn (int $i): array => [sprintf('a0000000-0000-4000-8000-%012d', $i), 0], range(1, 26)), [[SessionFixture::SESSION, 0], [SessionFixture::SESSION, 0]], [[SessionFixture::SESSION, -1]]] as $targets) {
            try {
                $this->preview($this->draft($targets));
                self::fail('Expected a rejection.');
            } catch (ChildrenException $invalid) {
                self::assertStringContainsString('Choose 1–25 distinct sessions', $invalid->getMessage());
            }
        }
        try {
            $this->preview($this->draft([[SessionFixture::SESSION, 0]], self::LEADER, self::LEADER));
            self::fail('Expected a rejection.');
        } catch (ChildrenException $invalid) {
            self::assertStringContainsString('different person to replace', $invalid->getMessage());
        }
        try {
            $this->preview($this->draft([['b0000000-0000-4000-8000-0000000000ff', 0]]));
            self::fail('Expected not found.');
        } catch (ChildrenException $missing) {
            self::assertStringContainsString('Session', $missing->getMessage());
        }
        $this->expectExceptionMessage('Worker');
        $this->preview($this->draft([[SessionFixture::SESSION, 0]], 'c0000000-0000-4000-8000-0000000000ff'));
    }

    public function testApplyLocksSessionsThenWorkersInUuidOrderAndBumpsVersionsAndAudits(): void
    {
        $this->world->addSession(self::SECOND, '2026-06-14T09:00:00Z', '2026-06-14T11:00:00Z');
        $this->world->addSession(self::THIRD, '2026-06-21T09:00:00Z', '2026-06-21T11:00:00Z');

        $this->apply($this->draft([[self::THIRD, 0], [SessionFixture::SESSION, 0], [self::SECOND, 0]], self::LEADER, null));

        self::assertSame(
            ['lock:session:'.SessionFixture::SESSION, 'lock:session:'.self::SECOND, 'lock:session:'.self::THIRD, 'lock:worker:'.self::LEADER],
            array_values(array_filter($this->log->entries, static fn (string $e): bool => str_starts_with($e, 'lock:'))),
        );
        $firstWrite = array_search('save:staff:LEADER:absent', $this->log->entries, true);
        self::assertGreaterThan(array_search('lock:worker:'.self::LEADER, $this->log->entries, true), $firstWrite, 'writes follow every lock');
        foreach ([SessionFixture::SESSION, self::SECOND, self::THIRD] as $id) {
            self::assertSame(1, $this->fixture->sessions[$id]->version);
            self::assertSame(SessionStatus::Planned, $this->fixture->sessions[$id]->status);
        }
        // The shared fixture keeps one staff row per worker, so count the writes instead.
        self::assertSame(3, count(array_keys($this->log->entries, 'save:staff:LEADER:absent', true)));
        self::assertSame(3, count(array_keys($this->log->entries, 'audit:session.team.planned', true)));
    }

    public function testApplyingAReplacementRemovesTheOldAssignmentAndKeepsTheOthers(): void
    {
        $this->assign(self::LEADER);
        $this->assign(self::HELPER, SessionFixture::SESSION, StaffRole::Assistant);

        $this->apply($this->draft([[SessionFixture::SESSION, 0]], self::HELPER, self::LEADER, StaffRole::Leader));

        self::assertContains('remove:'.self::LEADER, $this->log->entries);
        self::assertSame([[self::HELPER, 'LEADER']], array_map(static fn ($m): array => [$m->workerId, $m->role->value], $this->fixture->staff));
    }

    public function testApplyLocksBothWorkersInUuidOrderWhenReplacing(): void
    {
        $this->assign(self::HELPER);

        $this->apply($this->draft([[SessionFixture::SESSION, 0]], self::LEADER, self::HELPER));

        self::assertSame(['lock:worker:'.self::LEADER, 'lock:worker:'.self::HELPER], array_values(array_filter($this->log->entries, static fn (string $e): bool => str_starts_with($e, 'lock:worker'))));
    }

    public function testApplyRefusesBlockedPlansWithTheBlockersAndWritesNothing(): void
    {
        $this->assign(self::LEADER, SessionFixture::SESSION, StaffRole::Leader, true);
        $before = $this->fixture->staff;

        try {
            $this->apply($this->draft([[SessionFixture::SESSION, 0]], self::LEADER));
            self::fail('Expected a conflict.');
        } catch (ChildrenException $conflict) {
            self::assertSame('Confirmed presence must be managed on the individual session.', $conflict->getMessage());
        }
        self::assertSame($before, $this->fixture->staff);
        self::assertNotContains('audit:session.team.planned', $this->log->entries);
        self::assertSame([], array_filter($this->log->entries, static fn (string $e): bool => str_starts_with($e, 'update:') || str_starts_with($e, 'save:')));
    }

    public function testApplyNeedsAReasonAndKnownSessionsAndWorkers(): void
    {
        try {
            $this->apply($this->draft(), '  ');
            self::fail('Expected a rejection.');
        } catch (ChildrenException) {
            self::assertSame([], $this->log->entries);
        }
        try {
            $this->apply($this->draft([['b0000000-0000-4000-8000-0000000000ff', 0]]));
            self::fail('Expected not found.');
        } catch (ChildrenException $missing) {
            self::assertStringContainsString('Session', $missing->getMessage());
        }
        $this->expectExceptionMessage('Worker');
        $this->apply($this->draft([[SessionFixture::SESSION, 0]], 'c0000000-0000-4000-8000-0000000000ff'));
    }
}
