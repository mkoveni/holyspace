<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Application\Contract\Booking;
use App\Volunteering\Application\Contract\InvitationDetails;
use App\Volunteering\Application\Contract\InvitationRecord;
use App\Volunteering\Application\Contract\RosterDirectory;
use App\Volunteering\Application\Contract\ShiftDetails;
use App\Volunteering\Application\Contract\ShiftPage;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Application\Contract\TeamDirectory;
use App\Volunteering\Application\Contract\VolunteerDirectory;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/** Java `ServingViews` shift and invitation views. Reading never changes state: expiry is shown, not applied. */
final readonly class RosterViews
{
    public function __construct(
        private RosterDirectory $roster,
        private VolunteerDirectory $volunteers,
        private TeamDirectory $teams,
        private VolunteerNames $names,
        private VolunteerReadiness $readiness,
        private ClockInterface $clock,
    ) {
    }

    public function shift(string $id): ShiftDetails
    {
        $shift = $this->roster->shift($id) ?? throw VolunteerException::missing('Shift');
        $bookings = [];
        $reserved = $confirmed = $ready = 0;
        foreach ($this->roster->assignments($id) as $assignment) {
            $volunteer = $this->volunteers->volunteer($assignment->volunteerId) ?? throw VolunteerException::missing('Volunteer');
            $readiness = $this->readiness->assess($assignment->volunteerId, $shift, $assignment->id);
            $bookings[] = new Booking(
                $assignment,
                $this->names->summary($volunteer)->name,
                $readiness,
                array_map($this->invitation(...), $this->roster->invitations($assignment->id)),
            );
            if ('CANCELLED' !== $assignment->status) {
                ++$reserved;
            }
            if ('CONFIRMED' === $assignment->status) {
                ++$confirmed;
                if ($readiness->ready) {
                    ++$ready;
                }
            }
        }

        return new ShiftDetails($shift, $this->teamName($shift), $this->roleName($shift), $bookings, $reserved, $confirmed, $ready, max(0, $shift->positions - $reserved));
    }

    public function shifts(?DateTimeImmutable $from, ?DateTimeImmutable $until, ?string $teamId, int $limit, int $offset): ShiftPage
    {
        ServingViews::page($limit, $offset);
        [$from, $until] = ServingViews::range($from, $until);
        $shifts = array_map(fn (ShiftView $shift): ShiftDetails => $this->shift($shift->id), $this->roster->shifts($from, $until, $teamId, $limit, $offset));

        return new ShiftPage($shifts, $this->roster->shiftCount($from, $until, $teamId));
    }

    public function invitation(InvitationRecord $invitation): InvitationDetails
    {
        $assignment = $this->roster->assignment($invitation->assignmentId) ?? throw VolunteerException::missing('Assignment');
        $shift = $this->roster->shift($assignment->shiftId) ?? throw VolunteerException::missing('Shift');
        $status = $invitation->status;
        if ('PENDING' === $status) {
            if ('CANCELLED' === $assignment->status || 'CANCELLED' === $shift->status || $assignment->version !== $invitation->assignmentVersion
                || $shift->scheduleRevision !== $invitation->shiftVersion) {
                $status = 'WITHDRAWN';
            } elseif (!($invitation->expiresAt > $this->clock->now())) {
                $status = 'EXPIRED';
            }
        }

        return new InvitationDetails($invitation, $status, $this->teamName($shift), $this->roleName($shift), $shift->startsAt, $shift->endsAt, $this->readiness->assess($assignment->volunteerId, $shift, $assignment->id));
    }

    /** @return list<InvitationDetails> the invitations of the volunteer linked to the account; none without a link */
    public function own(string $accountId, int $limit, int $offset): array
    {
        ServingViews::page($limit, $offset);
        $volunteer = $this->volunteers->byAccount($accountId);

        return null === $volunteer ? [] : array_map($this->invitation(...), $this->roster->myInvitations($volunteer->id, $limit, $offset));
    }

    public function teamName(ShiftView $shift): string
    {
        return ($this->teams->team($shift->teamId) ?? throw VolunteerException::missing('Team'))->name;
    }

    public function roleName(ShiftView $shift): string
    {
        return ($this->teams->role($shift->roleId) ?? throw VolunteerException::missing('Role'))->name;
    }
}
