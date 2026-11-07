<?php

declare(strict_types=1);

namespace App\People\Application\Command\SetHouseholdContact;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\HouseholdRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class SetHouseholdContactHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private HouseholdRepository $households,
    ) {
    }

    public function handle(SetHouseholdContactCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $household = $this->households->findForUpdate($command->householdId) ?? throw PeopleException::missing('Household');
        $now = $this->clock->now();
        $member = null === $command->membershipId
            ? null
            : $this->households->member($household->id, $command->membershipId) ?? throw PeopleException::missing('Membership');
        $this->households->update($household->contact($member, $command->expectedVersion, $now));
        $this->audit->audit($command->actorId, 'household.contact.changed', $household->id, $now);
    }
}
