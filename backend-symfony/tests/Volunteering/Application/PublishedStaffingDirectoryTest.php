<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application;

use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Application\Contract\StaffingUnavailable;
use PHPUnit\Framework\TestCase;

final class PublishedStaffingDirectoryTest extends TestCase
{
    public function testAConfirmedAssignmentForTheSessionIsReady(): void
    {
        $scenario = new StaffingScenario();

        $result = $scenario->directory()->assess(StaffingScenario::ASSIGNMENT, StaffingScenario::SESSION, 'LEADER', $scenario->from, $scenario->until);

        self::assertTrue($result->ready);
        self::assertSame([], $result->issues);
        self::assertSame('Person '.StaffingScenario::PERSON, $result->name);
        self::assertSame('LEADER', $result->responsibility);
        self::assertSame(StaffingScenario::PERSON, $result->personId);
    }

    public function testSupervisionIssuesAreAppendedAfterReadinessIssuesInJavaOrder(): void
    {
        $scenario = new StaffingScenario();
        $scenario->data->assignments[StaffingScenario::ASSIGNMENT] = new AssignmentView(StaffingScenario::ASSIGNMENT, StaffingScenario::SHIFT, StaffingScenario::VOLUNTEER, 'PROPOSED');
        $scenario->data->exceptions = [];

        $result = $scenario->directory()->assess(
            StaffingScenario::ASSIGNMENT,
            '00000000-0000-4000-8000-0000000000ff',
            'ASSISTANT',
            $scenario->from->modify('-1 minute'),
            $scenario->until,
        );

        self::assertFalse($result->ready);
        self::assertSame([
            'Availability is unknown or does not cover the entire shift.',
            'The volunteer assignment is not confirmed.',
            'Choose an assignment linked to this exact children session.',
            'The role does not approve this children responsibility.',
            'The assignment must cover the entire session duty interval.',
        ], $result->issues);
    }

    public function testAnUnknownPersonIsNamedUnavailable(): void
    {
        $scenario = new StaffingScenario();
        $scenario->people = [];

        $result = $scenario->directory()->assess(StaffingScenario::ASSIGNMENT, StaffingScenario::SESSION, 'LEADER', $scenario->from, $scenario->until);

        self::assertSame('Unavailable person', $result->name);
        self::assertContains('The People record is unavailable.', $result->issues);
    }

    public function testDomainFailuresSurfaceAsStaffingUnavailable(): void
    {
        $scenario = new StaffingScenario();

        $this->expectException(StaffingUnavailable::class);
        $scenario->directory()->assess('missing', StaffingScenario::SESSION, 'LEADER', $scenario->from, $scenario->until);
    }

    public function testCandidatesSkipCancelledAssignmentsAndUseTheRolesResponsibility(): void
    {
        $scenario = new StaffingScenario();
        $scenario->data->assignments['cancelled'] = new AssignmentView('cancelled', StaffingScenario::SHIFT, StaffingScenario::VOLUNTEER, 'CANCELLED');

        $candidates = $scenario->directory()->candidates(StaffingScenario::SESSION, $scenario->from, $scenario->until);

        self::assertCount(1, $candidates);
        self::assertSame(StaffingScenario::ASSIGNMENT, $candidates[0]->assignmentId);
        self::assertSame('LEADER', $candidates[0]->responsibility);
        self::assertTrue($candidates[0]->ready);
    }

    public function testMoreThanAHundredShiftsIsUnavailable(): void
    {
        $scenario = new StaffingScenario();
        $shift = $scenario->shift();
        for ($i = 0; $i < 101; ++$i) {
            $scenario->data->shifts['s'.$i] = new ShiftView('s'.$i, $shift->teamId, $shift->roleId, $shift->sourceKind, $shift->sourceId, null, $shift->startsAt, $shift->endsAt, 'OPEN', 1);
        }

        $this->expectException(StaffingUnavailable::class);
        $scenario->directory()->candidates(StaffingScenario::SESSION, $scenario->from, $scenario->until);
    }

    public function testCandidatesWithAMissingRoleAreUnavailable(): void
    {
        $scenario = new StaffingScenario();
        $scenario->data->roles = [];

        $this->expectException(StaffingUnavailable::class);
        $scenario->directory()->candidates(StaffingScenario::SESSION, $scenario->from, $scenario->until);
    }
}
