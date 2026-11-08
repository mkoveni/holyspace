<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\UpdateServingRole;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\CredentialCatalogue;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\TeamLocks;
use App\Volunteering\Domain\Repository\TeamRepository;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/**
 * Renames, archives or changes a role's requirements; readiness reflects them at once. The children responsibility
 * cannot change (409). Unknown credential types are a 400 here, unlike role creation, as in Java.
 */
final readonly class UpdateServingRoleHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private TeamLocks $locks,
        private TeamRepository $teams,
        private CredentialCatalogue $catalogue,
        private ServingAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(UpdateServingRoleCommand $command): void
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $team = $this->locks->withActivities($command->teamId, $command->expectedVersion);
        $existing = $this->teams->findRole($command->roleId);
        if (null === $existing || $existing->teamId !== $team->id) {
            throw VolunteerException::missing('Team role');
        }
        $changed = $existing->change($command->name, $command->childrenRole, $command->active, $command->requiredCredentials);
        foreach ($changed->requiredCredentials as $type) {
            if (!$this->catalogue->knows($type)) {
                throw VolunteerException::invalid('Choose known credential types.');
            }
        }
        $this->teams->saveRole($changed);
        $this->teams->save($team->touch());
        $this->audit->record($command->actorId, 'team.role.changed', $changed->id, $this->clock->now());
    }
}
