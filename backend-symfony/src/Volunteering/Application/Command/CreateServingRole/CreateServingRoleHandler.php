<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\CreateServingRole;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\CredentialCatalogue;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\TeamLocks;
use App\Volunteering\Domain\Repository\TeamRepository;
use App\Volunteering\Domain\ServingRole;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/** Adds a role to a team. Every requirement must name a catalogue credential type (404 otherwise, as Java). */
final readonly class CreateServingRoleHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private TeamLocks $locks,
        private TeamRepository $teams,
        private CredentialCatalogue $catalogue,
        private ServingAudit $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(CreateServingRoleCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $team = $this->locks->withActivities($command->teamId, $command->expectedVersion);
        $requirements = $command->requiredCredentials ?? throw VolunteerException::invalid('Choose credential requirements from the catalogue.');
        foreach ($requirements as $type) {
            if (!$this->catalogue->knows($type)) {
                throw VolunteerException::missing('Credential type');
            }
        }
        $role = ServingRole::create($this->ids->uuid(), $team->id, $command->name, $command->childrenRole, $requirements);
        $this->teams->addRole($role);
        $this->teams->save($team->touch());
        $this->audit->record($command->actorId, 'team.role.created', $role->id, $this->clock->now());

        return $role->id;
    }
}
