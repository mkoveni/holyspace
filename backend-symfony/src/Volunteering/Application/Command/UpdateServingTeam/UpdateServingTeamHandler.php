<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\UpdateServingTeam;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\TeamLocks;
use App\Volunteering\Domain\Repository\TeamRepository;
use Psr\Clock\ClockInterface;

/** Renames or (de)activates a team. Lock order: the team's linked activities, then the team row. */
final readonly class UpdateServingTeamHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private TeamLocks $locks,
        private TeamRepository $teams,
        private ServingAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(UpdateServingTeamCommand $command): void
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $team = $this->locks->withActivities($command->teamId, $command->expectedVersion);
        $this->teams->save($team->change($command->name, $command->active));
        $this->audit->record($command->actorId, 'team.changed', $team->id, $this->clock->now());
    }
}
