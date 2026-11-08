<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\CreateServingTeam;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Domain\Repository\TeamRepository;
use App\Volunteering\Domain\Team;
use Psr\Clock\ClockInterface;

final readonly class CreateServingTeamHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private TeamRepository $teams,
        private ServingAudit $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(CreateServingTeamCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $team = Team::create($this->ids->uuid(), $command->name);
        $this->teams->insert($team);
        $this->audit->record($command->actorId, 'team.created', $team->id, $this->clock->now());

        return $team->id;
    }
}
