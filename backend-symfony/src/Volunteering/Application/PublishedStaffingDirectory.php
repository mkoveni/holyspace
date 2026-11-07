<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\People\Application\Contract\PersonDirectory;
use App\Volunteering\Application\Contract\RosterReader;
use App\Volunteering\Application\Contract\StaffingAssessment;
use App\Volunteering\Application\Contract\StaffingDirectory;
use App\Volunteering\Application\Contract\StaffingUnavailable;
use App\Volunteering\Application\Contract\TeamReader;
use App\Volunteering\Application\Contract\VolunteerReader;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;

/** Children Ministry's view of volunteer readiness; domain failures are hidden behind {@see StaffingUnavailable}. */
final readonly class PublishedStaffingDirectory implements StaffingDirectory
{
    private const MAX_SOURCE_SHIFTS = 100;

    public function __construct(
        private RosterReader $roster,
        private VolunteerReader $volunteers,
        private TeamReader $teams,
        private VolunteerReadiness $readiness,
        private PersonDirectory $people,
    ) {
    }

    public function assess(string $assignmentId, string $sessionId, string $responsibility, DateTimeImmutable $from, DateTimeImmutable $until): StaffingAssessment
    {
        try {
            return $this->assessment($assignmentId, $sessionId, $responsibility, $from, $until);
        } catch (VolunteerException) {
            throw new StaffingUnavailable();
        }
    }

    public function candidates(string $sessionId, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $shifts = $this->roster->sourceShifts('CHILDREN_SESSION', $sessionId, self::MAX_SOURCE_SHIFTS + 1);
        if (\count($shifts) > self::MAX_SOURCE_SHIFTS) {
            throw new StaffingUnavailable();
        }
        $candidates = [];
        foreach ($shifts as $shift) {
            if ('CHILDREN_SESSION' !== $shift->sourceKind || $sessionId !== $shift->sourceId) {
                continue;
            }
            foreach ($this->roster->assignments($shift->id) as $assignment) {
                if ('CANCELLED' === $assignment->status) {
                    continue;
                }
                $role = $this->teams->role($shift->roleId) ?? throw new StaffingUnavailable();
                $candidates[] = $this->assess($assignment->id, $sessionId, $role->childrenRole, $from, $until);
            }
        }

        return $candidates;
    }

    private function assessment(string $id, string $sessionId, string $responsibility, DateTimeImmutable $from, DateTimeImmutable $until): StaffingAssessment
    {
        $assignment = $this->roster->assignment($id) ?? throw VolunteerException::missing('Volunteer assignment');
        $shift = $this->roster->shift($assignment->shiftId) ?? throw VolunteerException::missing('Shift');
        $volunteer = $this->volunteers->volunteer($assignment->volunteerId) ?? throw VolunteerException::missing('Volunteer');
        $role = $this->teams->role($shift->roleId) ?? throw VolunteerException::missing('Role');
        $issues = $this->readiness->assess($volunteer->id, $shift, $id)->issues;
        if ('CONFIRMED' !== $assignment->status) {
            $issues[] = 'The volunteer assignment is not confirmed.';
        }
        if ('CHILDREN_SESSION' !== $shift->sourceKind || $sessionId !== $shift->sourceId) {
            $issues[] = 'Choose an assignment linked to this exact children session.';
        }
        if ($role->childrenRole !== $responsibility) {
            $issues[] = 'The role does not approve this children responsibility.';
        }
        if (!ServingRules::covers($shift->startsAt, $shift->endsAt, $from, $until)) {
            $issues[] = 'The assignment must cover the entire session duty interval.';
        }
        $name = $this->people->get($volunteer->personId)->name ?? 'Unavailable person';

        return new StaffingAssessment($id, $volunteer->personId, $name, $role->childrenRole, [] === $issues, $issues);
    }
}
