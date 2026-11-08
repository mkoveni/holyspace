<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RegisterVolunteer;

use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\Contract\VolunteerDirectory;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Domain\Repository\VolunteerRepository;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\Volunteer;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/**
 * Java `VolunteerProfiles.register`: one profile per available person, optionally linked to an active account
 * (linking also needs identity.manage). The person row is locked first, so two registrations of one person
 * serialize; a parallel duplicate of the person or account is a clash.
 */
final readonly class RegisterVolunteerHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private PersonDirectory $people,
        private VolunteerDirectory $directory,
        private AccountDirectory $accounts,
        private VolunteerRepository $volunteers,
        private ServingAudit $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RegisterVolunteerCommand $command): string
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        if (null !== $command->accountId) {
            $this->auth->requirePermission(ServingPermissions::IDENTITY);
        }
        $personId = ServingRules::id($command->personId);
        if (true !== $this->people->lock($personId)?->available) {
            throw VolunteerException::missing('Available person');
        }
        if (null !== $this->directory->byPerson($personId)) {
            throw VolunteerException::conflict('This person already has a volunteer profile.');
        }
        if (null !== $command->accountId && !$this->accounts->active($command->accountId)) {
            throw VolunteerException::invalid('Choose an active account.');
        }
        $now = $this->clock->now();
        $volunteer = Volunteer::register($this->ids->uuid(), $personId, $command->accountId, $now);
        $this->volunteers->insert($volunteer);
        $this->audit->record($command->actorId, 'volunteer.registered', $volunteer->id, $now);

        return $volunteer->id;
    }
}
