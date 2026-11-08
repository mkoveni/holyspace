<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\CreateCredentialType;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Domain\CredentialType;
use App\Volunteering\Domain\Repository\VolunteerRepository;
use Psr\Clock\ClockInterface;

/**
 * Java `VolunteerProfiles.type`: adds a catalogue entry. A duplicate code is a clash.
 */
final readonly class CreateCredentialTypeHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerRepository $volunteers,
        private ServingAudit $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(CreateCredentialTypeCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::CREDENTIALS);
        $type = CredentialType::create($this->ids->uuid(), $command->code, $command->name);
        $this->volunteers->addCredentialType($type);
        $this->audit->record($command->actorId, 'credential.type.created', $type->id, $this->clock->now());

        return $type->id;
    }
}
