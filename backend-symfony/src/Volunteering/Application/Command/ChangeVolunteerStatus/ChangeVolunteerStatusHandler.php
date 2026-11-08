<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ChangeVolunteerStatus;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\VolunteerLocks;
use App\Volunteering\Domain\Repository\VolunteerRepository;
use App\Volunteering\Domain\ServingRules;
use Psr\Clock\ClockInterface;

/**
 * Java `VolunteerProfiles.status`: pauses, archives or reactivates a profile at the expected version. Existing
 * assignments stay; readiness reports the change.
 */
final readonly class ChangeVolunteerStatusHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerLocks $locks,
        private VolunteerRepository $volunteers,
        private ServingAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ChangeVolunteerStatusCommand $command): void
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $volunteer = $this->locks->lock($command->volunteerId);
        ServingRules::version($volunteer->version, $command->expectedVersion);
        $now = $this->clock->now();
        $this->volunteers->save($volunteer->change($command->status, $now));
        $this->audit->record($command->actorId, 'volunteer.status', $volunteer->id, $now);
    }
}
