<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RevokeVolunteerCredential;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\Contract\VolunteerDirectory;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\VolunteerLocks;
use App\Volunteering\Domain\Repository\VolunteerRepository;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/**
 * Java `VolunteerProfiles.revoke`: revokes one of the volunteer's credentials with a reason. Readiness stops
 * counting it at once; confirmed assignments stay confirmed.
 */
final readonly class RevokeVolunteerCredentialHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerLocks $locks,
        private VolunteerDirectory $directory,
        private VolunteerRepository $volunteers,
        private ServingAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RevokeVolunteerCredentialCommand $command): void
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::CREDENTIALS);
        $reason = ServingRules::text($command->reason, 500);
        $volunteer = $this->locks->lock($command->volunteerId);
        ServingRules::version($volunteer->version, $command->expectedVersion);
        $held = false;
        foreach ($this->directory->credentials($volunteer->id) as $credential) {
            $held = $held || $credential->id === $command->credentialId;
        }
        if (!$held) {
            throw VolunteerException::missing('Credential');
        }
        $now = $this->clock->now();
        $this->volunteers->revokeCredential($command->credentialId, $now);
        $this->volunteers->save($volunteer->touch($now));
        $this->audit->record($command->actorId, 'credential.revoked', $command->credentialId, $now, $reason);
    }
}
