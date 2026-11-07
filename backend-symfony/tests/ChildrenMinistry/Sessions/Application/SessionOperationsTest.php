<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Contract\VolunteerStaffLink;
use App\ChildrenMinistry\Sessions\Application\SessionView;
use App\ChildrenMinistry\Sessions\Application\StaffScope;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use App\Tests\Support\AllowedPermissions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionOperationsTest extends TestCase
{
    private const string LEADER = '10000000-0000-4000-8000-000000000001';
    private const string HELPER = '10000000-0000-4000-8000-000000000002';
    private const string OTHER = '10000000-0000-4000-8000-000000000003';
    private const string NOW = '2026-06-01T00:00:00Z';

    private function world(): SessionFixture
    {
        return new SessionFixture(new CallLog());
    }

    private function view(SessionFixture $world): SessionView
    {
        return $world->operations()->view($world->sessions[SessionFixture::SESSION], new DateTimeImmutable(self::NOW));
    }

    public function testAViewNamesTheClassRoomAndZoneAndCountsReadyStaff(): void
    {
        $world = $this->world();
        $world->serve(self::LEADER, StaffRole::Leader);
        $world->serve(self::HELPER, StaffRole::Assistant);
        $world->occupancy = 3;

        $view = $this->view($world);

        self::assertSame(['Juniors', 'Room 1', 'Africa/Johannesburg'], [$view->className, $view->roomName, $view->timeZone]);
        self::assertSame(4, $view->effectiveCapacity, 'two ready adults at two children each, below 10 seats and a room of 12');
        self::assertSame(3, $view->occupancy);
        self::assertFalse($view->staffingNeedsReview);
        self::assertSame([[], []], array_map(static fn ($staff): array => $staff->issues, $view->staff));
    }

    public function testNoLeaderOrTooFewAdultsMeansNoCapacityAndReview(): void
    {
        $world = $this->world();
        $world->serve(self::HELPER, StaffRole::Assistant);

        $view = $this->view($world);

        self::assertSame(0, $view->effectiveCapacity);
        self::assertTrue($view->staffingNeedsReview);

        $world->minStaff = 2;
        $world->serve(self::LEADER, StaffRole::Leader);
        self::assertSame(4, $this->view($world)->effectiveCapacity);
        $world->minStaff = 3;
        self::assertSame(0, $this->view($world)->effectiveCapacity);
    }

    public function testChildrenAboveCapacityNeedReview(): void
    {
        $world = $this->world();
        $world->serve(self::LEADER, StaffRole::Leader);
        $world->occupancy = 1;
        self::assertFalse($this->view($world)->staffingNeedsReview);
        $world->occupancy = 2;
        self::assertFalse($this->view($world)->staffingNeedsReview, 'exactly full (one adult, two children each) is not over capacity');
        $world->occupancy = 3;
        self::assertTrue($this->view($world)->staffingNeedsReview);
    }

    public function testAnAbsentWorkerIsListedButNotCounted(): void
    {
        $world = $this->world();
        $world->serve(self::LEADER, StaffRole::Leader, present: false);

        $view = $this->view($world);

        self::assertCount(1, $view->staff);
        self::assertFalse($view->staff[0]->present);
        self::assertSame(0, $view->effectiveCapacity);
    }

    public function testEachReasonAWorkerCannotBeCountedIsReportedAndExcludesThem(): void
    {
        $world = $this->world();
        $world->serve(self::LEADER, StaffRole::Leader, screenedUntil: '2026-06-07T10:00:00Z');
        $world->serve(self::HELPER, StaffRole::Assistant, birth: '2012-01-01');
        $world->serve(self::OTHER, StaffRole::Assistant);
        $world->accounts['acct-'.self::OTHER] = false;

        $view = $this->view($world);

        $issues = [];
        foreach ($view->staff as $staff) {
            $issues[$staff->personId] = $staff->issues;
        }
        self::assertSame(['Screening/training must cover this session and clearance must not be revoked.'], $issues[self::LEADER]);
        self::assertSame(['A recorded birth date confirming adulthood is required.'], $issues[self::HELPER]);
        self::assertSame(['Linked account is inactive.'], $issues[self::OTHER]);
        self::assertSame(0, $view->effectiveCapacity);
        self::assertTrue($view->staffingNeedsReview);
    }

    public function testAnArchivedPersonAndAMissingBirthDateAreReported(): void
    {
        $world = $this->world();
        $world->serve(self::LEADER, StaffRole::Leader);
        $world->people[self::LEADER] = ['Gone Person', null];

        $issues = $this->view($world)->staff[0]->issues;

        self::assertSame(['Person is archived or deceased.', 'A recorded birth date confirming adulthood is required.'], $issues);
    }

    public function testAdulthoodIsJudgedOnTheSessionDayInTheLocationsZone(): void
    {
        $world = $this->world();
        $world->serve(self::LEADER, StaffRole::Leader, birth: '2008-06-08');
        $world->sessions[SessionFixture::SESSION] = MinistrySession::reconstitute(SessionFixture::SESSION, SessionFixture::CLASS_ID, SessionFixture::ROOM, new DateTimeImmutable('2026-06-07T22:30:00Z'), new DateTimeImmutable('2026-06-07T23:30:00Z'), 10, SessionStatus::Planned, 0);

        self::assertSame([], $this->view($world)->staff[0]->issues, '00:30 on the 8th in Johannesburg: they turn 18 that day');
        self::assertSame('2026-06-08', $world->operations()->date($world->sessions[SessionFixture::SESSION])->iso());
    }

    public function testALinkedVolunteerAssignmentContributesItsIssuesOrIsUnavailable(): void
    {
        $world = $this->world();
        $world->serve(self::LEADER, StaffRole::Leader);
        $world->serve(self::HELPER, StaffRole::Assistant);
        $world->links[self::LEADER] = new VolunteerStaffLink('a1', 'LEADER');
        $world->links[self::HELPER] = new VolunteerStaffLink('a2', 'ASSISTANT');
        $world->assessments['a1'] = ['The volunteer has not confirmed.'];

        $issues = [];
        foreach ($this->view($world)->staff as $staff) {
            $issues[$staff->personId] = $staff->issues;
        }

        self::assertSame(['The volunteer has not confirmed.'], $issues[self::LEADER]);
        self::assertSame(['The linked volunteer assignment is unavailable.'], $issues[self::HELPER]);
    }

    public function testAMissingRelatedRecordIsReportedByName(): void
    {
        $world = $this->world();
        $world->staff[] = new SessionStaff(SessionFixture::SESSION, self::LEADER, StaffRole::Leader, true);

        try {
            $this->view($world);
            self::fail('Expected a rejection.');
        } catch (ChildrenException $error) {
            self::assertSame('Worker was not found.', $error->getMessage());
        }
    }

    public function testRequireSpaceRefusesAFullOrUnderstaffedSession(): void
    {
        $world = $this->world();
        $now = new DateTimeImmutable(self::NOW);
        $session = $world->sessions[SessionFixture::SESSION];
        $operations = $world->operations();

        try {
            $operations->requireSpace($session, $now);
            self::fail('Expected a rejection.');
        } catch (ChildrenException $error) {
            self::assertSame('Session is full or its present staffing needs review.', $error->getMessage());
        }

        $world->serve(self::LEADER, StaffRole::Leader);
        $world->occupancy = 1;
        $operations->requireSpace($session, $now);
        $world->occupancy = 2;
        $this->expectException(ChildrenException::class);
        $operations->requireSpace($session, $now);
    }

    /** @param list<string> $permissions */
    private function scope(SessionFixture $world, array $permissions): StaffScope
    {
        return new StaffScope(new AllowedPermissions($permissions), $world);
    }

    public function testStationUsersMustBePresentOnTheSessionUnlessSupervisors(): void
    {
        $world = $this->world();
        $world->serve(self::LEADER, StaffRole::Leader);
        $world->serve(self::HELPER, StaffRole::Assistant, present: false);
        $scope = fn (array $permissions): StaffScope => $this->scope($world, array_values($permissions));

        $scope(['children.station'])->require('acct-'.self::LEADER, SessionFixture::SESSION);
        $scope(['children.supervise'])->require('nobody', SessionFixture::SESSION);

        foreach (['acct-'.self::HELPER, 'nobody'] as $account) {
            try {
                $scope(['children.station'])->require($account, SessionFixture::SESSION);
                self::fail('Expected a refusal.');
            } catch (ChildrenException $error) {
                self::assertSame('This operation is outside your assigned session.', $error->getMessage());
            }
        }
        $this->expectException(ChildrenException::class);
        $scope(['children.station'])->require('acct-'.self::LEADER, 'e0000000-0000-4000-8000-0000000000ff');
    }
}
