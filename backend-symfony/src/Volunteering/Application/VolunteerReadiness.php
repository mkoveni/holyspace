<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\People\Application\Contract\PersonDirectory;
use App\Volunteering\Application\Contract\RosterReader;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Application\Contract\TeamReader;
use App\Volunteering\Application\Contract\VolunteerReader;
use App\Volunteering\Domain\Availability;
use App\Volunteering\Domain\ValidityWindow;
use App\Volunteering\Domain\VolunteerException;

/** Every reason a volunteer cannot work a shift, reported in a fixed order. */
final readonly class VolunteerReadiness
{
    private const MAX_BOOKINGS = 1000;

    public function __construct(
        private VolunteerReader $volunteers,
        private TeamReader $teams,
        private RosterReader $roster,
        private PersonDirectory $people,
        private ActivityRegistry $activities,
    ) {
    }

    /** @throws VolunteerException when the volunteer, role or bookings cannot be assessed */
    public function assess(string $volunteerId, ShiftView $shift, ?string $ignoreAssignment): Readiness
    {
        $volunteer = $this->volunteers->volunteer($volunteerId) ?? throw VolunteerException::missing('Volunteer');
        $role = $this->teams->role($shift->roleId) ?? throw VolunteerException::missing('Role');
        $issues = [];
        if ('ACTIVE' !== $volunteer->status) {
            $issues[] = 'The volunteer is paused or archived.';
        }
        if (true !== $this->people->get($volunteer->personId)?->available) {
            $issues[] = 'The People record is unavailable.';
        }
        if (true !== $this->teams->team($shift->teamId)?->active || !$role->active) {
            $issues[] = 'The team or role is inactive.';
        }
        if ('OPEN' !== $shift->status) {
            $issues[] = 'The shift is cancelled.';
        }
        try {
            $this->activities->require($shift);
        } catch (VolunteerException $invalid) {
            $issues[] = $invalid->getMessage();
        }
        if (!$this->anyCovers($this->teams->memberships($shift->teamId, $volunteerId), $shift)) {
            $issues[] = 'Team membership must cover the entire shift.';
        }
        if (!$this->anyCovers($this->teams->approvals($shift->roleId, $volunteerId), $shift)) {
            $issues[] = 'Role approval must cover the entire shift.';
        }
        $credentials = $this->volunteers->credentials($volunteerId);
        foreach ($role->requiredCredentials as $required) {
            $held = false;
            foreach ($credentials as $credential) {
                if ($credential->typeId === $required && $credential->window->covers($shift->startsAt, $shift->endsAt)) {
                    $held = true;
                    break;
                }
            }
            if (!$held) {
                $issues[] = $this->credentialName($required).' must be verified and valid through the end of this shift.';
            }
        }
        if (!Availability::covers(
            $shift->startsAt,
            $shift->endsAt,
            $this->volunteers->availability($volunteerId, $volunteer->availabilityRevision),
            $this->volunteers->exceptions($volunteerId, $volunteer->availabilityRevision),
        )) {
            $issues[] = 'Availability is unknown or does not cover the entire shift.';
        }
        $bookings = $this->roster->volunteerAssignments($volunteerId, $shift->startsAt, $shift->endsAt);
        if (\count($bookings) > self::MAX_BOOKINGS) {
            throw VolunteerException::conflict('Too many assignments in this interval. Narrow the schedule.');
        }
        foreach ($bookings as $booking) {
            if ($booking->id !== $ignoreAssignment) {
                $issues[] = 'There is an overlapping assignment.';
                break;
            }
        }

        return new Readiness([] === $issues, $issues);
    }

    /** @param list<ValidityWindow> $windows */
    private function anyCovers(array $windows, ShiftView $shift): bool
    {
        foreach ($windows as $window) {
            if ($window->covers($shift->startsAt, $shift->endsAt)) {
                return true;
            }
        }

        return false;
    }

    private function credentialName(string $typeId): string
    {
        foreach ($this->volunteers->credentialTypes() as $type) {
            if ($type->id === $typeId) {
                return $type->name;
            }
        }

        return 'Required credential';
    }
}
