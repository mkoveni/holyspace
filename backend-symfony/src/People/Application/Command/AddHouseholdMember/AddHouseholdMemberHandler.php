<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddHouseholdMember;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Domain\HouseholdMembership;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\HouseholdRepository;
use App\People\Domain\Repository\PersonRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class AddHouseholdMemberHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private HouseholdRepository $households,
        private PersonRepository $people,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new membership id */
    public function handle(AddHouseholdMemberCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $household = $this->households->findForUpdate($command->householdId) ?? throw PeopleException::missing('Household');
        $person = $this->people->findForUpdate($command->personId) ?? throw PeopleException::missing('Person');
        $person->requireAvailable();
        $now = $this->clock->now();
        $this->households->update($household->touch($command->expectedVersion, $now));
        $member = HouseholdMembership::start($this->ids->uuid(), $household->id, $person->id, $command->primary, $now);
        $this->households->add($member);
        $this->audit->audit($command->actorId, 'household.member.added', $household->id, $now);

        return $member->id;
    }
}
