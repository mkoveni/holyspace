<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\JoinServingTeam;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\TeamLocks;
use App\Volunteering\Application\VolunteerLocks;
use App\Volunteering\Domain\Repository\TeamRepository;
use App\Volunteering\Domain\TeamMembership;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/**
 * Adds a volunteer to an active team for a period that overlaps none of their unrevoked memberships. Lock order: the
 * volunteer ({@see VolunteerLocks}: person, linked activities, volunteer row), then the team row.
 */
final readonly class JoinServingTeamHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerLocks $volunteers,
        private TeamLocks $locks,
        private TeamRepository $teams,
        private ServingAudit $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(JoinServingTeamCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $volunteer = $this->volunteers->lock($command->volunteerId);
        $team = $this->locks->team($command->teamId, $command->expectedVersion);
        $team->requireActive();
        $membership = TeamMembership::grant($this->ids->uuid(), $team->id, $volunteer->id, $command->validFrom, $command->validUntil);
        if ($membership->overlapsAny(...$this->teams->membershipWindows($team->id, $volunteer->id))) {
            throw VolunteerException::conflict('A team membership already covers part of this interval.');
        }
        $this->teams->addMembership($membership);
        $this->teams->save($team->touch());
        $this->audit->record($command->actorId, 'team.member.added', $membership->id, $this->clock->now());

        return $membership->id;
    }
}
