<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RecordVolunteerCredential;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\CredentialCatalogue;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\VolunteerLocks;
use App\Volunteering\Domain\Credential;
use App\Volunteering\Domain\Repository\VolunteerRepository;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/**
 * Java `VolunteerProfiles.credential`: records a verified credential of a catalogue type, with its evidence note,
 * at the expected profile version. Lock order as `VolunteerLocks::lock`.
 */
final readonly class RecordVolunteerCredentialHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerLocks $locks,
        private CredentialCatalogue $catalogue,
        private VolunteerRepository $volunteers,
        private ServingAudit $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RecordVolunteerCredentialCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::CREDENTIALS);
        $volunteer = $this->locks->lock($command->volunteerId);
        ServingRules::version($volunteer->version, $command->expectedVersion);
        if (!$this->catalogue->knows($command->typeId)) {
            throw VolunteerException::missing('Credential type');
        }
        $now = $this->clock->now();
        $credential = Credential::record($this->ids->uuid(), $volunteer->id, (string) $command->typeId, $command->validFrom, $command->validUntil, $command->evidence, $command->actorId, $now);
        $this->volunteers->addCredential($credential);
        $this->volunteers->save($volunteer->touch($now));
        $this->audit->record($command->actorId, 'credential.verified', $credential->id, $now);

        return $credential->id;
    }
}
