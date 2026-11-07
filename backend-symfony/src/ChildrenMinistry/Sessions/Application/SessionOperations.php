<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Application\Contract\VenueReader;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Application\Contract\VolunteerStaffLinks;
use App\ChildrenMinistry\Sessions\Domain\CapacityPolicy;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Domain\CalendarDate;
use App\Volunteering\Application\Contract\StaffingDirectory;
use App\Volunteering\Application\Contract\StaffingUnavailable;
use DateTimeImmutable;
use DateTimeZone;

/** Builds the operational picture of a session: who is staffed, how many children it can hold, how many it holds. */
final readonly class SessionOperations
{
    public function __construct(
        private SessionReader $sessions,
        private ParticipationReader $participation,
        private VenueReader $venues,
        private AttendanceReader $attendance,
        private PersonDirectory $people,
        private AccountDirectory $accounts,
        private StaffingDirectory $volunteerStaffing,
        private VolunteerStaffLinks $volunteerLinks,
    ) {
    }

    /** The session's start as a calendar date in its location's time zone. */
    public function date(MinistrySession $session): CalendarDate
    {
        return CalendarDate::fromInstant($session->startsAt, new DateTimeZone($this->venue($session)['timeZone']));
    }

    public function view(MinistrySession $session, DateTimeImmutable $now): SessionView
    {
        $group = $this->participation->ministryClass($session->classId) ?? throw ChildrenException::missing('Class');
        $programme = $this->participation->programme($group->programmeId) ?? throw ChildrenException::missing('Programme');
        $venue = $this->venue($session);
        $on = CalendarDate::fromInstant($session->startsAt, new DateTimeZone($venue['timeZone']));

        $staff = [];
        foreach ($this->sessions->staff($session->id) as $assignment) {
            $worker = $this->sessions->worker($assignment->workerId) ?? throw ChildrenException::missing('Worker');
            $person = $this->people->get($worker->personId) ?? throw ChildrenException::missing('Person');
            $issues = [];
            $link = $this->volunteerLinks->find($session->id, $worker->personId);
            if (null !== $link) {
                try {
                    $assessment = $this->volunteerStaffing->assess($link->assignmentId, $session->id, $assignment->role->value, $session->startsAt, $session->endsAt);
                    array_push($issues, ...$assessment->issues);
                } catch (StaffingUnavailable) {
                    $issues[] = 'The linked volunteer assignment is unavailable.';
                }
            }
            if (!$worker->eligible($now, $session->endsAt)) {
                $issues[] = 'Screening/training must cover this session and clearance must not be revoked.';
            }
            if (!$this->accounts->active($worker->accountId)) {
                $issues[] = 'Linked account is inactive.';
            }
            if (!$person->available) {
                $issues[] = 'Person is archived or deceased.';
            }
            try {
                ChildrenRules::adult($person->birthDate, $on);
            } catch (ChildrenException $invalid) {
                $issues[] = $invalid->getMessage();
            }
            $staff[] = new StaffView($person->id, $person->name, $assignment->role, $assignment->present, $issues);
        }

        $eligible = count(array_filter($staff, static fn (StaffView $value): bool => $value->counts()));
        $leader = [] !== array_filter($staff, static fn (StaffView $value): bool => $value->counts() && StaffRole::Leader === $value->role);
        $capacity = CapacityPolicy::capacity($session->seatLimit, $venue['capacity'], $eligible, $leader, $programme->minStaff, $programme->childrenPerStaff);
        $count = $this->attendance->occupancy($session->id);

        return new SessionView($session, $group->name, $venue['roomName'], $venue['timeZone'], $capacity, $count, 0 === $capacity || $count > $capacity, $staff);
    }

    public function requireSpace(MinistrySession $session, DateTimeImmutable $now): void
    {
        $view = $this->view($session, $now);
        if ($view->staffingNeedsReview || $view->occupancy >= $view->effectiveCapacity) {
            throw ChildrenException::conflict('Session is full or its present staffing needs review.');
        }
    }

    /** @return array{capacity: int, roomName: string, timeZone: string} the session's room and its location's time zone */
    private function venue(MinistrySession $session): array
    {
        $room = $this->venues->room($session->roomId) ?? throw ChildrenException::missing('Room');
        $location = $this->venues->location($room->locationId) ?? throw ChildrenException::missing('Location');

        return ['capacity' => $room->capacity, 'roomName' => $room->name, 'timeZone' => $location->timeZone];
    }
}
