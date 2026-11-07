<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application;

use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\CredentialView;
use App\Volunteering\Application\Contract\RoleView;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Application\Contract\SourceActivity;
use App\Volunteering\Application\Contract\TeamView;
use App\Volunteering\Application\Contract\VolunteerView;
use App\Volunteering\Domain\ValidityWindow;
use App\Volunteering\Domain\VolunteerException;
use PHPUnit\Framework\TestCase;

final class VolunteerReadinessTest extends TestCase
{
    public function testAReadyVolunteerHasNoIssues(): void
    {
        $scenario = new StaffingScenario();

        $result = $scenario->readiness()->assess(StaffingScenario::VOLUNTEER, $scenario->shift(), StaffingScenario::ASSIGNMENT);

        self::assertTrue($result->ready);
        self::assertSame([], $result->issues);
    }

    public function testPausedVolunteerAndUnavailablePersonAreReported(): void
    {
        $scenario = new StaffingScenario();
        $scenario->data->volunteers[StaffingScenario::VOLUNTEER] = new VolunteerView(StaffingScenario::VOLUNTEER, StaffingScenario::PERSON, 'PAUSED', 0);
        $scenario->people[StaffingScenario::PERSON] = false;

        self::assertSame(
            ['The volunteer is paused or archived.', 'The People record is unavailable.'],
            $this->issues($scenario),
        );
    }

    public function testInactiveTeamOrRoleAndCancelledShiftAreReported(): void
    {
        $scenario = new StaffingScenario();
        $scenario->data->teams[StaffingScenario::TEAM] = new TeamView(StaffingScenario::TEAM, false);
        $shift = $scenario->shift();
        $scenario->data->shifts[StaffingScenario::SHIFT] = new ShiftView($shift->id, $shift->teamId, $shift->roleId, $shift->sourceKind, $shift->sourceId, $shift->sourceRevision, $shift->startsAt, $shift->endsAt, 'CANCELLED', $shift->positions);

        self::assertSame(['The team or role is inactive.', 'The shift is cancelled.'], $this->issues($scenario));
    }

    public function testRescheduledCancelledOrUnknownActivitiesAreReported(): void
    {
        $scenario = new StaffingScenario();
        $scenario->source->activities[StaffingScenario::SESSION] = new SourceActivity(StaffingScenario::SESSION, 'Sunday', $scenario->from->modify('+1 hour'), $scenario->until, true);
        self::assertSame(['The linked activity was rescheduled. Review the duty and update the shift before proceeding.'], $this->issues($scenario));

        $scenario->source->activities[StaffingScenario::SESSION] = new SourceActivity(StaffingScenario::SESSION, 'Sunday', $scenario->from, $scenario->until, false);
        self::assertSame(['The linked activity is cancelled, closed or unavailable.'], $this->issues($scenario));

        $scenario->source->activities = [];
        self::assertSame(['The linked activity is cancelled, closed or unavailable.'], $this->issues($scenario));
    }

    public function testMembershipAndApprovalMustCoverTheWholeShift(): void
    {
        $scenario = new StaffingScenario();
        $partial = new ValidityWindow($scenario->from->modify('+1 minute'), $scenario->until->modify('+1 day'));
        $scenario->data->memberships = [$partial];
        $scenario->data->approvals = [new ValidityWindow($scenario->from, $scenario->until, $scenario->from)];

        self::assertSame(['Team membership must cover the entire shift.', 'Role approval must cover the entire shift.'], $this->issues($scenario));
    }

    public function testRequiredCredentialsMustBeVerifiedAndValidThroughTheShift(): void
    {
        $scenario = new StaffingScenario();
        $scenario->data->roles[StaffingScenario::ROLE] = new RoleView(StaffingScenario::ROLE, StaffingScenario::TEAM, 'LEADER', true, [StaffingScenario::CREDENTIAL_TYPE, 'unlisted']);
        $scenario->data->credentials = [new CredentialView(StaffingScenario::CREDENTIAL_TYPE, new ValidityWindow($scenario->from, $scenario->until->modify('-1 second')))];

        self::assertSame([
            'Police clearance must be verified and valid through the end of this shift.',
            'Required credential must be verified and valid through the end of this shift.',
        ], $this->issues($scenario));

        $scenario->data->credentials = [new CredentialView(StaffingScenario::CREDENTIAL_TYPE, new ValidityWindow($scenario->from, $scenario->until))];
        self::assertSame(['Required credential must be verified and valid through the end of this shift.'], $this->issues($scenario));
    }

    public function testUnknownAvailabilityIsReported(): void
    {
        $scenario = new StaffingScenario();
        $scenario->data->exceptions = [];

        self::assertSame(['Availability is unknown or does not cover the entire shift.'], $this->issues($scenario));
    }

    public function testOverlappingAssignmentsConflictExceptTheOneBeingAssessed(): void
    {
        $scenario = new StaffingScenario();
        $scenario->data->bookings = [new AssignmentView(StaffingScenario::ASSIGNMENT, StaffingScenario::SHIFT, StaffingScenario::VOLUNTEER, 'CONFIRMED')];
        self::assertSame([], $this->issues($scenario));

        $scenario->data->bookings[] = new AssignmentView('other', 'shift', StaffingScenario::VOLUNTEER, 'PROPOSED');
        self::assertSame(['There is an overlapping assignment.'], $this->issues($scenario));
    }

    public function testTooManyBookingsIsAnError(): void
    {
        $scenario = new StaffingScenario();
        $scenario->data->bookings = array_fill(0, 1001, new AssignmentView('x', 'shift', StaffingScenario::VOLUNTEER, 'CONFIRMED'));

        $this->expectException(VolunteerException::class);
        $scenario->readiness()->assess(StaffingScenario::VOLUNTEER, $scenario->shift(), StaffingScenario::ASSIGNMENT);
    }

    public function testMissingVolunteerOrRoleIsAnError(): void
    {
        $scenario = new StaffingScenario();
        $scenario->data->roles = [];

        $this->expectException(VolunteerException::class);
        $scenario->readiness()->assess(StaffingScenario::VOLUNTEER, $scenario->shift(), null);
    }

    /** @return list<string> */
    private function issues(StaffingScenario $scenario): array
    {
        return $scenario->readiness()->assess(StaffingScenario::VOLUNTEER, $scenario->shift(), StaffingScenario::ASSIGNMENT)->issues;
    }
}
