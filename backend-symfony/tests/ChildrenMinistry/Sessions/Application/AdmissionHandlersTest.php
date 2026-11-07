<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Safeguarding\Application\GuardianAuthorityView;
use App\ChildrenMinistry\Sessions\Application\Command\CheckInChild\CheckInChildCommand;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\IdentityAccess\Domain\IdentityException;

/** Check-in is the risky door: permission, then locks in a fixed order, then every admission rule, then the writes. */
final class AdmissionHandlersTest extends AttendanceScenario
{
    private function command(string $child = self::CHILD, string $request = self::REQUEST, string $session = SessionFixture::SESSION, string $actor = self::STATION): CheckInChildCommand
    {
        return new CheckInChildCommand($actor, $session, $child, $request);
    }

    public function testCheckInLocksTheChildThenTheSessionThenChecksThenWrites(): void
    {
        $view = $this->checkIn()->handle($this->command());

        self::assertSame([
            'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:session', 'read:arrival', 'read:occupancy',
            'read:enrolmentAt', 'read:assignmentAt', 'insert:attendance', 'insert:segment', 'insert:event:ARRIVED', 'audit:child.checked.in',
        ], $this->log->entries);
        self::assertSame([self::CHILD, SessionFixture::SESSION, 0, false, null], [$view->childId, $view->sessionId, $view->version, $view->labelIssued, $view->checkedOutAt]);
        self::assertSame(['e1', 'a1', 'c1'], [$this->world->segments[0]->enrolmentId, $this->world->segments[0]->assignmentId, $this->world->segments[0]->consentId]);
        self::assertNull($this->world->segments[0]->leftAt);
    }

    public function testNothingIsLockedWithoutThePermission(): void
    {
        try {
            $this->checkIn(['children.read'])->handle($this->command());
            self::fail('Expected a refusal.');
        } catch (IdentityException) {
        }

        self::assertSame([], $this->log->entries);
    }

    public function testStaffNotPresentOnTheSessionAreRefusedAfterTheLocksAndBeforeAnyWrite(): void
    {
        $this->world->staff = [];
        $this->world->serve(self::LEADER, StaffRole::Leader, present: false);

        $this->refused('This operation is outside your assigned session.', fn () => $this->checkIn()->handle($this->command()));

        self::assertSame(['person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:session'], $this->log->entries);
    }

    public function testSupervisorsMayAdmitOnAnySession(): void
    {
        $this->checkIn(['children.station', 'children.supervise'])->handle($this->command(actor: 'someone-else'));

        self::assertCount(1, $this->world->attendances);
    }

    public function testAnUnknownSessionIsMissingAfterTheChildIsLocked(): void
    {
        $this->refused('MinistrySession was not found.', fn () => $this->checkIn()->handle($this->command(session: 'e0000000-0000-4000-8000-0000000000ff')));

        self::assertSame(['person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:session'], $this->log->entries);
    }

    public function testRetryingWithTheSameRequestReturnsTheSameVisitAndWritesNothing(): void
    {
        $first = $this->checkIn()->handle($this->command());
        $this->log->entries = [];

        $again = $this->checkIn()->handle($this->command());

        self::assertSame($first->id, $again->id);
        self::assertSame(['person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:session', 'read:arrival'], $this->log->entries);
        self::assertCount(1, $this->world->attendances);
    }

    public function testARequestIdCannotBeReusedForAnotherChild(): void
    {
        $this->checkIn()->handle($this->command());
        $other = '30000000-0000-4000-8000-000000000003';

        $this->refused('Arrival request ID was already used for another arrival.', fn () => $this->checkIn()->handle($this->command(child: $other)));
    }

    public function testArrivalsNeedAnOpenSessionWithinThirtyMinutesOfStart(): void
    {
        $message = 'Arrivals require an open session, from 30 minutes before its start until its end.';
        $this->clock->modify('-16 minutes');
        $this->refused($message, fn () => $this->checkIn()->handle($this->command()));
        $this->clock->modify('+16 minutes');

        $this->world->sessions[SessionFixture::SESSION] = $this->world->sessions[SessionFixture::SESSION]->withStatus(SessionStatus::NeedsReview, 0);
        $this->refused($message, fn () => $this->checkIn()->handle($this->command()));
        self::assertNotContains('insert:attendance', $this->log->entries);
    }

    public function testASessionWithoutSpaceOrCoverRefusesArrival(): void
    {
        $this->world->occupancy = 2;
        $this->refused('Session is full or its present staffing needs review.', fn () => $this->checkIn()->handle($this->command()));
        $this->world->occupancy = 0;
        $this->world->staff = [];
        $this->refused('Session is full or its present staffing needs review.', fn () => $this->checkIn(['children.station', 'children.supervise'])->handle($this->command()));
    }

    public function testTheChildMustFitTheClassAgeRangeOnTheSessionDay(): void
    {
        $this->refused("Child is outside this class's age range.", fn () => $this->checkIn(childBirth: '2024-01-01')->handle($this->command()));
        $this->refused('A recorded birth date identifying a child under 18 is required.', fn () => $this->checkIn(childBirth: '2000-01-01')->handle($this->command()));
        self::assertNotContains('insert:attendance', $this->log->entries);
    }

    public function testEnrolmentAndPlacementMustBeCurrentAndMatch(): void
    {
        unset($this->world->enrolments[SessionFixture::PROGRAMME.self::CHILD]);
        $this->refused('Active programme enrolment is required for the session date.', fn () => $this->checkIn()->handle($this->command()));

        $this->admit(self::CHILD);
        unset($this->world->placements[SessionFixture::CLASS_ID.self::CHILD]);
        $this->refused('A current class assignment is required for the session date.', fn () => $this->checkIn()->handle($this->command()));

        $this->admit(self::CHILD);
        $this->world->placements[SessionFixture::CLASS_ID.self::CHILD] = new \App\ChildrenMinistry\Participation\Application\AssignmentView('a1', SessionFixture::PROGRAMME, SessionFixture::CLASS_ID, self::CHILD, 'another-enrolment', \App\Shared\Domain\CalendarDate::parse('2026-01-01'), \App\Shared\Domain\CalendarDate::parse('2027-01-01'), null, 0);
        $this->refused('A current class assignment is required for the session date.', fn () => $this->checkIn()->handle($this->command()));
    }

    public function testConsentMustCoverTheWholeSessionUnderACoveringAuthority(): void
    {
        $message = 'Current guardian consent for the programme policy must cover the session.';
        $consent = $this->safeguarding->consents[0];

        $this->safeguarding->consents = [];
        $this->refused($message, fn () => $this->checkIn()->handle($this->command()));

        $this->safeguarding->consents = [new \App\ChildrenMinistry\Safeguarding\Application\ConsentView('c1', $consent->programmeId, $consent->policyId, $consent->childId, $consent->authorityId, $consent->validFrom, $this->at('2026-06-07T10:59:59Z'), $consent->acceptedAt, $consent->recordedBy, $consent->evidenceReference, null, null, 0)];
        $this->refused($message, fn () => $this->checkIn()->handle($this->command()));

        $this->safeguarding->consents = [$consent];
        $this->safeguarding->authorities[self::AUTHORITY] = new GuardianAuthorityView(self::AUTHORITY, self::CHILD, 'guardian', $this->at('2026-01-01T00:00:00Z'), $this->at('2026-06-07T10:00:00Z'), 'ID', self::ACTOR, null, null, 0);
        $this->refused($message, fn () => $this->checkIn()->handle($this->command()));

        $this->safeguarding->authorities[self::AUTHORITY] = new GuardianAuthorityView(self::AUTHORITY, self::CHILD, 'guardian', $this->at('2026-01-01T00:00:00Z'), $this->at('2027-01-01T00:00:00Z'), 'ID', self::ACTOR, $this->at('2026-06-01T00:00:00Z'), 'Revoked', 1);
        $this->refused($message, fn () => $this->checkIn()->handle($this->command()));

        $this->world->policyId = null;
        $this->admit(self::CHILD);
        $this->refused($message, fn () => $this->checkIn()->handle($this->command()));
        self::assertSame([], $this->world->attendances);
    }
}
