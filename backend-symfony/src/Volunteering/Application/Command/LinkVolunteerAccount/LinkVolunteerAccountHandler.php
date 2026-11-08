<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\LinkVolunteerAccount;

use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Volunteering\Application\Contract\ServingAudit;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\VolunteerLocks;
use App\Volunteering\Domain\Repository\VolunteerRepository;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\VolunteerException;
use Psr\Clock\ClockInterface;

/**
 * Java `VolunteerProfiles.account`: links (or with null unlinks) a login. Needs volunteering.manage and
 * identity.manage. An account linked to another volunteer is a clash. Linking grants no permission.
 */
final readonly class LinkVolunteerAccountHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerLocks $locks,
        private AccountDirectory $accounts,
        private VolunteerRepository $volunteers,
        private ServingAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(LinkVolunteerAccountCommand $command): void
    {
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::MANAGE);
        $this->auth->requireActorPermission($command->actorId, ServingPermissions::IDENTITY);
        $volunteer = $this->locks->lock($command->volunteerId);
        ServingRules::version($volunteer->version, $command->expectedVersion);
        if (null !== $command->accountId && !$this->accounts->active($command->accountId)) {
            throw VolunteerException::invalid('Choose an active account.');
        }
        $now = $this->clock->now();
        $this->volunteers->save($volunteer->link($command->accountId, $now));
        $this->audit->record($command->actorId, 'volunteer.account.changed', $volunteer->id, $now);
    }
}
