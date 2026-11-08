<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RespondMyVolunteerInvitation;

use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\Contract\RosterDirectory;
use App\Volunteering\Application\OwnVolunteer;
use App\Volunteering\Application\RosterPlanning;
use App\Volunteering\Domain\VolunteerException;

/**
 * Java `MemberVolunteerActions.respond`: the caller answers an invitation of their own linked profile; another
 * volunteer's invitation is a 404. The profile is locked and access rechecked before the answer.
 */
final readonly class RespondMyVolunteerInvitationHandler implements CommandHandler
{
    public function __construct(
        private OwnVolunteer $own,
        private RosterDirectory $roster,
        private RosterPlanning $planning,
    ) {
    }

    public function handle(RespondMyVolunteerInvitationCommand $command): void
    {
        [$accountId, $before] = $this->own->profile();
        $invitation = $this->roster->invitation($command->invitationId) ?? throw VolunteerException::missing('Invitation');
        if ($this->roster->assignment($invitation->assignmentId)?->volunteerId !== $before->id) {
            throw VolunteerException::missing('Invitation');
        }
        $this->own->lock($accountId, $before->id);
        $this->planning->respond($accountId, $accountId, $command->invitationId, $command->expectedVersion, $command->decision);
    }
}
