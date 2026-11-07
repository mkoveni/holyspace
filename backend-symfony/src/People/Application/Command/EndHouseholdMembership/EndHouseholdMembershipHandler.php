<?php

declare(strict_types=1);

namespace App\People\Application\Command\EndHouseholdMembership;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\HouseholdRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class EndHouseholdMembershipHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private HouseholdRepository $households,
    ) {
    }

    public function handle(EndHouseholdMembershipCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $household = $this->households->findForUpdate($command->householdId) ?? throw PeopleException::missing('Household');
        $member = $this->households->member($household->id, $command->membershipId) ?? throw PeopleException::missing('Membership');
        $now = $this->clock->now();
        $updated = $member->id === $household->primaryContactMembershipId
            ? $household->contact(null, $command->expectedVersion, $now)
            : $household->touch($command->expectedVersion, $now);
        $this->households->update($updated);
        $this->households->end($member->end($now));
        $this->audit->audit($command->actorId, 'household.member.ended', $household->id, $now);
    }
}
