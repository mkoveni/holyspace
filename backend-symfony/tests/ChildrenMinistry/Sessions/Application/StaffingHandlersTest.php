<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Command\ImportServingStaff\ImportServingStaffCommand;
use App\ChildrenMinistry\Sessions\Application\Command\ImportServingStaff\ImportServingStaffHandler;
use App\ChildrenMinistry\Sessions\Application\Command\RegisterWorker\RegisterWorkerCommand;
use App\ChildrenMinistry\Sessions\Application\Command\RegisterWorker\RegisterWorkerHandler;
use App\ChildrenMinistry\Sessions\Application\Command\ReviewWorker\ReviewWorkerCommand;
use App\ChildrenMinistry\Sessions\Application\Command\ReviewWorker\ReviewWorkerHandler;
use App\ChildrenMinistry\Sessions\Application\Command\SetSessionStaff\SetSessionStaffCommand;
use App\ChildrenMinistry\Sessions\Application\Command\SetSessionStaff\SetSessionStaffHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListServingCandidates\ListServingCandidatesHandler;
use App\ChildrenMinistry\Sessions\Application\Query\ListServingCandidates\ListServingCandidatesQuery;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\ChurchCalendar;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\ChildrenMinistry\Shared\RecordingChildLocks;
use App\Tests\ChildrenMinistry\Shared\RecordingPeople;
use App\Tests\Support\AllowedPermissions;
use App\Volunteering\Application\Contract\StaffingAssessment;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Staffing and worker clearance: permission first, then locks in a fixed order, then rules, then writes and audit. */
final class StaffingHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string LEADER = '10000000-0000-4000-8000-000000000001';
    private const string HELPER = '10000000-0000-4000-8000-000000000002';
    private const string ASSIGNMENT = '20000000-0000-4000-8000-000000000001';

    private CallLog $log;
    private SessionFixture $world;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new CallLog();
        $this->world = new SessionFixture($this->log, SessionStatus::Open);
        $this->clock = new MockClock('2026-06-07 08:30:00 UTC');
    }

    /** @param list<string> $permissions */
    private function staffHandler(array $permissions = ['children.manage']): SetSessionStaffHandler
    {
        return new SetSessionStaffHandler(new AllowedPermissions($permissions), new RecordingChildrenAudit($this->log), $this->clock, $this->world, $this->world, $this->world->operations());
    }

    private function setStaff(string $worker = self::HELPER, StaffRole $role = StaffRole::Assistant, bool $present = true, int $version = 0): SetSessionStaffCommand
    {
        return new SetSessionStaffCommand(self::ACTOR, SessionFixture::SESSION, $version, $worker, $role, $present);
    }

    private function refused(string $message, callable $action): void
    {
        try {
            $action();
            self::fail('Expected a rejection.');
        } catch (ChildrenException $error) {
            self::assertSame($message, $error->getMessage());
        }
    }

    public function testStaffingLocksSessionThenWorkerThenWritesAndAudits(): void
    {
        $this->world->serve(self::LEADER, StaffRole::Leader);
        $this->world->serve(self::HELPER, StaffRole::Assistant, present: false);
        $this->log->entries = [];

        $this->staffHandler()->handle($this->setStaff());

        self::assertSame(['forUpdate:session', 'forUpdate:worker', 'read:elsewhere', 'save:staff:ASSISTANT:present', 'read:occupancy', 'update:session:OPEN', 'audit:session.staff.changed'], $this->log->entries);
        self::assertSame(1, $this->world->sessions[SessionFixture::SESSION]->version);
    }

    public function testNothingIsTouchedWithoutPermission(): void
    {
        try {
            $this->staffHandler(['children.read'])->handle($this->setStaff());
            self::fail('Expected a refusal.');
        } catch (IdentityException) {
        }

        self::assertSame([], $this->log->entries);
    }

    public function testTerminalSessionsAndUnknownRecordsAreRefusedBeforeAnyWrite(): void
    {
        $this->world->serve(self::HELPER);
        $this->refused('Worker was not found.', fn () => $this->staffHandler()->handle($this->setStaff('10000000-0000-4000-8000-0000000000ff')));
        $this->refused('MinistrySession was not found.', fn () => $this->staffHandler()->handle(new SetSessionStaffCommand(self::ACTOR, 'e0000000-0000-4000-8000-0000000000ff', 0, self::HELPER, StaffRole::Assistant, true)));
        $this->world->sessions[SessionFixture::SESSION] = $this->world->sessions[SessionFixture::SESSION]->withStatus(SessionStatus::Cancelled, 0);
        $this->log->entries = [];

        $this->refused('Terminal session staffing cannot change.', fn () => $this->staffHandler()->handle($this->setStaff()));

        self::assertSame(['forUpdate:session'], $this->log->entries);
    }

    public function testAWorkerCannotBePresentInTwoOverlappingSessionsButMayBeMarkedAbsent(): void
    {
        $this->world->serve(self::HELPER);
        $this->world->elsewhere[self::HELPER] = true;
        $this->log->entries = [];

        $this->refused('Worker is already present in an overlapping session. Mark them absent there first.', fn () => $this->staffHandler()->handle($this->setStaff()));
        self::assertSame(['forUpdate:session', 'forUpdate:worker', 'read:elsewhere'], $this->log->entries);

        $this->staffHandler()->handle($this->setStaff(present: false));
        self::assertNotContains('read:elsewhere', array_slice($this->log->entries, 3), 'absence is never checked against other sessions');
    }

    public function testAStaleVersionIsCheckedBeforeAnythingIsSaved(): void
    {
        $this->world->serve(self::HELPER);
        $this->log->entries = [];

        $this->refused('This record changed. Reload before trying again.', fn () => $this->staffHandler()->handle($this->setStaff(version: 3)));

        self::assertSame(['forUpdate:session', 'forUpdate:worker', 'read:elsewhere'], $this->log->entries);
    }

    public function testAnOpenSessionThatLosesItsLeaderNeedsReview(): void
    {
        $this->world->serve(self::LEADER, StaffRole::Leader);
        $this->staffHandler()->handle($this->setStaff(self::LEADER, StaffRole::Leader, false));

        self::assertSame(SessionStatus::NeedsReview, $this->world->sessions[SessionFixture::SESSION]->status);
    }

    public function testAPlannedSessionStaysPlannedWhateverTheStaffing(): void
    {
        $this->world = new SessionFixture($this->log, SessionStatus::Planned);
        $this->world->serve(self::HELPER);

        $this->staffHandler()->handle($this->setStaff());

        self::assertSame(SessionStatus::Planned, $this->world->sessions[SessionFixture::SESSION]->status);
        self::assertSame(1, $this->world->sessions[SessionFixture::SESSION]->version);
    }

    public function testTheAuditRecordsWhoWasStaffedInWhichRole(): void
    {
        $this->world->serve(self::LEADER, StaffRole::Leader);

        $this->staffHandler()->handle($this->setStaff(self::LEADER, StaffRole::Leader, true));

        self::assertSame(StaffRole::Leader, $this->world->staff(SessionFixture::SESSION)[0]->role);
        self::assertTrue($this->world->staff(SessionFixture::SESSION)[0]->present);
        self::assertSame('audit:session.staff.changed', (string) end($this->log->entries));
    }

    private function access(string $birth = '1990-01-01'): ParticipantAccess
    {
        return new ParticipantAccess(new RecordingPeople($this->log, [self::LEADER => true], [self::LEADER => $birth]), new RecordingChildLocks($this->log));
    }

    /** @param list<string> $permissions */
    private function register(array $permissions = ['children.safeguarding'], string $birth = '1990-01-01'): RegisterWorkerHandler
    {
        return new RegisterWorkerHandler(new AllowedPermissions($permissions), new RecordingChildrenAudit($this->log), $this->clock, $this->access($birth), $this->world, $this->world, new ChurchCalendar($this->clock, 'Africa/Johannesburg'));
    }

    /** @param list<string> $permissions */
    private function review(array $permissions = ['children.safeguarding'], string $birth = '1990-01-01'): ReviewWorkerHandler
    {
        return new ReviewWorkerHandler(new AllowedPermissions($permissions), new RecordingChildrenAudit($this->log), $this->clock, $this->world, $this->access($birth), $this->world, new ChurchCalendar($this->clock, 'Africa/Johannesburg'));
    }

    private function registerCommand(?string $evidence = 'Police clearance'): RegisterWorkerCommand
    {
        return new RegisterWorkerCommand(self::ACTOR, self::LEADER, 'acct-'.self::LEADER, new DateTimeImmutable('2027-01-01T00:00:00Z'), new DateTimeImmutable('2027-02-01T00:00:00Z'), $evidence);
    }

    public function testRegisteringLocksThePersonChecksAdulthoodAndAccountThenInserts(): void
    {
        $this->world->accounts['acct-'.self::LEADER] = true;

        $this->register()->handle($this->registerCommand());

        self::assertSame(['person:'.self::LEADER, 'guard:'.self::LEADER, 'insert:worker', 'audit:worker.verified'], $this->log->entries);
        $worker = $this->world->workers[self::LEADER];
        self::assertSame([0, self::ACTOR, null, 'Police clearance'], [$worker->version, $worker->verifiedBy, $worker->revokedAt, $worker->evidenceReference]);
        self::assertEquals($this->clock->now(), $worker->verifiedFrom);
    }

    public function testRegisteringRefusesMinorsInactiveAccountsAndBadWindows(): void
    {
        $this->world->accounts['acct-'.self::LEADER] = true;
        $this->refused('A recorded birth date confirming adulthood is required.', fn () => $this->register(birth: '2012-01-01')->handle($this->registerCommand()));
        $this->world->accounts['acct-'.self::LEADER] = false;
        $this->refused('Link an active account to the verified worker.', fn () => $this->register()->handle($this->registerCommand()));
        $this->world->accounts['acct-'.self::LEADER] = true;
        $this->refused('A value is missing or exceeds its maximum length of 255.', fn () => $this->register()->handle($this->registerCommand(null)));
        $this->refused('Validity end must follow its start.', fn () => $this->register()->handle(new RegisterWorkerCommand(self::ACTOR, self::LEADER, 'acct-'.self::LEADER, new DateTimeImmutable('2026-06-07T08:30:00Z'), new DateTimeImmutable('2027-01-01T00:00:00Z'), 'x')));

        self::assertNotContains('insert:worker', $this->log->entries);
    }

    public function testRegisteringAndReviewingNeedTheSafeguardingPermission(): void
    {
        foreach ([fn () => $this->register(['children.manage'])->handle($this->registerCommand()), fn () => $this->review(['children.manage'])->handle($this->reviewCommand())] as $action) {
            try {
                $action();
                self::fail('Expected a refusal.');
            } catch (IdentityException) {
            }
        }

        self::assertSame([], $this->log->entries);
    }

    private function reviewCommand(int $version = 0, bool $revoked = false): ReviewWorkerCommand
    {
        return new ReviewWorkerCommand(self::ACTOR, self::LEADER, $version, new DateTimeImmutable('2028-01-01T00:00:00Z'), new DateTimeImmutable('2028-02-01T00:00:00Z'), 'Renewed', $revoked);
    }

    public function testReviewingRenewsClearanceFromNowAndBumpsTheVersion(): void
    {
        $this->world->serve(self::LEADER, screenedUntil: '2026-12-01T00:00:00Z');
        $this->log->entries = [];

        $this->review()->handle($this->reviewCommand());

        self::assertSame(['person:'.self::LEADER, 'guard:'.self::LEADER, 'forUpdate:worker', 'update:worker', 'audit:worker.reviewed'], $this->log->entries);
        $worker = $this->world->workers[self::LEADER];
        self::assertEquals([$this->clock->now(), new DateTimeImmutable('2028-01-01T00:00:00Z'), 1, self::ACTOR, null], [$worker->verifiedFrom, $worker->screenedUntil, $worker->version, $worker->verifiedBy, $worker->revokedAt]);
    }

    public function testRevokingKeepsTheOriginalStartAndStampsTheRevocation(): void
    {
        $this->world->serve(self::LEADER);
        $this->world->accounts['acct-'.self::LEADER] = false;
        $this->world->people[self::LEADER] = ['x', null];
        $original = $this->world->workers[self::LEADER]->verifiedFrom;

        $this->review(birth: '2015-01-01')->handle($this->reviewCommand(revoked: true));

        $worker = $this->world->workers[self::LEADER];
        self::assertEquals([$original, $this->clock->now()], [$worker->verifiedFrom, $worker->revokedAt]);
        self::assertFalse($worker->eligible($this->clock->now(), $this->clock->now()));
    }

    public function testReviewRefusesStaleVersionsUnknownWorkersAndInactiveAccounts(): void
    {
        $this->refused('Worker was not found.', fn () => $this->review()->handle($this->reviewCommand()));
        $this->world->serve(self::LEADER);
        $this->refused('This record changed. Reload before trying again.', fn () => $this->review()->handle($this->reviewCommand(2)));
        $this->world->accounts['acct-'.self::LEADER] = false;
        $this->refused('Worker account is inactive.', fn () => $this->review()->handle($this->reviewCommand()));
        $this->refused('A recorded birth date confirming adulthood is required.', fn () => $this->review(birth: '2015-01-01')->handle($this->reviewCommand()));

        self::assertNotContains('update:worker', $this->log->entries);
    }

    private function importHandler(): ImportServingStaffHandler
    {
        return new ImportServingStaffHandler(new AllowedPermissions(['children.manage']), new FixturePeople($this->world), $this->world, $this->world, $this->world, $this->world, $this->staffHandler());
    }

    private function importCommand(string $responsibility = 'ASSISTANT'): ImportServingStaffCommand
    {
        return new ImportServingStaffCommand(self::ACTOR, SessionFixture::SESSION, 0, self::ASSIGNMENT, $responsibility, true);
    }

    private function ready(): void
    {
        $this->world->serve(self::HELPER, present: false);
        $this->world->assessments[self::ASSIGNMENT] = [];
        $this->world->assignmentPerson[self::ASSIGNMENT] = self::HELPER;
        $this->log->entries = [];
    }

    public function testImportingAssessesLocksPersonThenSessionReassessesLinksAndStaffs(): void
    {
        $this->ready();

        $this->importHandler()->handle($this->importCommand());

        self::assertSame(['assess', 'person:'.self::HELPER, 'forUpdate:session', 'assess', 'link:ASSISTANT', 'forUpdate:session', 'forUpdate:worker', 'read:elsewhere', 'save:staff:ASSISTANT:present', 'assess', 'read:occupancy', 'update:session:NEEDS_REVIEW', 'audit:session.staff.changed'], $this->log->entries);
        self::assertSame(SessionStatus::NeedsReview, $this->world->sessions[SessionFixture::SESSION]->status, 'an open session with no leader needs review');
        self::assertSame(self::ASSIGNMENT, $this->world->links[self::HELPER]->assignmentId);
    }

    public function testImportingRefusesAnAssignmentThatIsNotReadyOrNotAWorker(): void
    {
        $this->ready();
        $this->world->assessments[self::ASSIGNMENT] = ['Not confirmed.', 'Credential expired.'];
        $this->refused('Not confirmed. Credential expired.', fn () => $this->importHandler()->handle($this->importCommand()));

        $this->world->assessments[self::ASSIGNMENT] = [];
        unset($this->world->workers[self::HELPER]);
        $this->refused('Register and verify this person as a Children Ministry worker in Spaces & team first.', fn () => $this->importHandler()->handle($this->importCommand()));

        self::assertNotContains('link:ASSISTANT', $this->log->entries);
    }

    public function testImportingReportsAVanishedAssignmentAndABadRole(): void
    {
        $this->ready();
        unset($this->world->assessments[self::ASSIGNMENT]);
        $this->refused('The serving assignment is no longer available. Refresh the roster.', fn () => $this->importHandler()->handle($this->importCommand()));

        $this->world->assessments[self::ASSIGNMENT] = [];
        $this->refused('Choose leader or assistant.', fn () => $this->importHandler()->handle($this->importCommand('COOK')));
        self::assertNotContains('link:COOK', $this->log->entries);
    }

    private function candidates(): ListServingCandidatesHandler
    {
        return new ListServingCandidatesHandler(new AllowedPermissions(['children.manage']), $this->world, $this->world);
    }

    public function testCandidatesAreLeaderAndAssistantAssignmentsMarkedWithWorkerRegistration(): void
    {
        $this->world->serve(self::HELPER);
        $this->world->candidates = [
            new StaffingAssessment('a1', self::HELPER, 'Helper', 'ASSISTANT', true, []),
            new StaffingAssessment('a2', self::LEADER, 'Lead', 'LEADER', false, ['Pending']),
            new StaffingAssessment('a3', self::LEADER, 'Cook', 'KITCHEN', true, []),
        ];

        $found = $this->candidates()->handle(new ListServingCandidatesQuery(self::ACTOR, SessionFixture::SESSION));

        self::assertSame([['a1', true], ['a2', false]], array_map(static fn ($candidate): array => [$candidate->assignment->assignmentId, $candidate->workerRegistered], $found));
    }

    public function testCandidatesReportAnUnavailableDirectoryAsAConflict(): void
    {
        $this->world->candidates = null;

        $this->refused('Serving assignments are unavailable or exceed the review limit. Review the serving schedule.', fn () => $this->candidates()->handle(new ListServingCandidatesQuery(self::ACTOR, SessionFixture::SESSION)));
        $this->refused('Session was not found.', fn () => $this->candidates()->handle(new ListServingCandidatesQuery(self::ACTOR, 'e0000000-0000-4000-8000-0000000000ff')));
    }
}
