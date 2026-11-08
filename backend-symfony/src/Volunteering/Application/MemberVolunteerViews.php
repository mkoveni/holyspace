<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\MemberDuty;
use App\Volunteering\Application\Contract\MemberInvitation;
use App\Volunteering\Application\Contract\MemberOverview;
use App\Volunteering\Application\Contract\RosterDirectory;
use App\Volunteering\Application\Contract\VolunteerDirectory;
use App\Volunteering\Application\Contract\VolunteerView;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/**
 * Java `MemberVolunteerViews`: a linked volunteer's own duties for the next 90 days (cancelled ones included) and their
 * current availability. Nothing about other volunteers, credential evidence or staff notes.
 */
final readonly class MemberVolunteerViews
{
    private const int HORIZON_SECONDS = 90 * 86400;

    public function __construct(
        private VolunteerDirectory $volunteers,
        private RosterDirectory $roster,
        private RosterViews $views,
        private VolunteerReadiness $readiness,
        private ActivityRegistry $activities,
        private ClockInterface $clock,
    ) {
    }

    public function overview(string $accountId, bool $permitted, int $limit, int $offset): MemberOverview
    {
        ServingViews::page($limit, $offset);
        if (!$permitted) {
            return MemberOverview::empty(false);
        }
        $volunteer = $this->volunteers->byAccount($accountId);

        return null === $volunteer ? MemberOverview::empty(true) : $this->of($volunteer, $limit, $offset);
    }

    private function of(VolunteerView $volunteer, int $limit, int $offset): MemberOverview
    {
        $now = $this->clock->now();
        $page = $this->roster->duties($volunteer->id, $now, $now->modify('+'.self::HORIZON_SECONDS.' seconds'), $limit + 1, $offset);

        return new MemberOverview(
            true,
            true,
            $volunteer->id,
            $volunteer->status,
            $volunteer->version,
            $this->volunteers->availability($volunteer->id, $volunteer->availabilityRevision),
            $this->volunteers->exceptions($volunteer->id, $volunteer->availabilityRevision),
            array_map($this->duty(...), \array_slice($page, 0, $limit)),
            \count($page) > $limit,
        );
    }

    private function duty(AssignmentView $assignment): MemberDuty
    {
        $shift = $this->roster->shift($assignment->shiftId) ?? throw VolunteerException::missing('Shift');
        $activity = $this->activities->find($shift->sourceKind, $shift->sourceId);
        $invitations = $this->roster->invitations($assignment->id);
        $invitation = null;
        if ([] !== $invitations) {
            $details = $this->views->invitation($invitations[0]);
            $invitation = new MemberInvitation($details->invitation->id, $details->invitation->version, $details->effectiveStatus, $details->invitation->expiresAt);
        }

        return new MemberDuty(
            $assignment->id,
            $assignment->status,
            $shift->status,
            $this->views->teamName($shift),
            $this->views->roleName($shift),
            $activity?->label,
            $activity?->venueLabel,
            $shift->startsAt,
            $shift->endsAt,
            $this->readiness->assess($assignment->volunteerId, $shift, $assignment->id),
            $invitation,
        );
    }
}
