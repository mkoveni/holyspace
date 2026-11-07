<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application;

use App\Volunteering\Application\ActivityStaffingSummary;
use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Application\Contract\StaffingSummary;
use App\Volunteering\Application\Contract\VolunteerView;
use PHPUnit\Framework\TestCase;

final class ActivityStaffingSummaryTest extends TestCase
{
    public function testUnreadyAndProvisionalAssignmentsNeverFillGaps(): void
    {
        $scenario = new StaffingScenario();
        $first = $scenario->shift();
        $second = $this->shift($scenario, 'second', 'OPEN', 1);
        $scenario->data->volunteers['paused'] = new VolunteerView('paused', StaffingScenario::PERSON, 'PAUSED', 0);
        $scenario->data->assignments = [
            StaffingScenario::ASSIGNMENT => new AssignmentView(StaffingScenario::ASSIGNMENT, $first->id, StaffingScenario::VOLUNTEER, 'CONFIRMED'),
            'unready' => new AssignmentView('unready', 'second', 'paused', 'CONFIRMED'),
            'proposed' => new AssignmentView('proposed', 'second', StaffingScenario::VOLUNTEER, 'PROPOSED'),
        ];

        $summary = $this->summary($scenario);

        self::assertSame(2, $summary->shifts);
        self::assertSame(2, $summary->positions);
        self::assertSame(2, $summary->confirmed);
        self::assertSame(1, $summary->ready);
        self::assertSame(1, $summary->proposed);
        self::assertSame(1, $summary->gaps);
        self::assertTrue($summary->complete);
        self::assertSame('second', $second->id);
    }

    public function testExtraCoverageInOneDutyDoesNotHideAGapInAnother(): void
    {
        $scenario = new StaffingScenario();
        $this->shift($scenario, 'second', 'OPEN', 1);
        $scenario->data->assignments = [
            'a' => new AssignmentView('a', StaffingScenario::SHIFT, StaffingScenario::VOLUNTEER, 'CONFIRMED'),
            'b' => new AssignmentView('b', StaffingScenario::SHIFT, StaffingScenario::VOLUNTEER, 'CONFIRMED'),
        ];

        self::assertSame(1, $this->summary($scenario)->gaps);
    }

    public function testCancelledShiftsAreIgnored(): void
    {
        $scenario = new StaffingScenario();
        $this->shift($scenario, 'cancelled', 'CANCELLED', 5);

        $summary = $this->summary($scenario);

        self::assertSame(1, $summary->shifts);
        self::assertSame(1, $summary->positions);
    }

    public function testMoreThanTwoHundredShiftsIsReportedIncomplete(): void
    {
        $scenario = new StaffingScenario();
        for ($i = 0; $i < 200; ++$i) {
            $this->shift($scenario, 's'.$i, 'OPEN', 1);
        }

        $summary = $this->summary($scenario);

        self::assertSame(200, $summary->shifts);
        self::assertFalse($summary->complete);
    }

    private function summary(StaffingScenario $scenario): StaffingSummary
    {
        return new ActivityStaffingSummary($scenario->data, $scenario->readiness())->summarize('CHILDREN_SESSION', StaffingScenario::SESSION);
    }

    private function shift(StaffingScenario $scenario, string $id, string $status, int $positions): ShiftView
    {
        $base = $scenario->shift();

        return $scenario->data->shifts[$id] = new ShiftView($id, $base->teamId, $base->roleId, $base->sourceKind, $base->sourceId, $base->sourceRevision, $base->startsAt, $base->endsAt, $status, $positions);
    }
}
