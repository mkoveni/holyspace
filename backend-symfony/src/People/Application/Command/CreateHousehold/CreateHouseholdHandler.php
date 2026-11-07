<?php

declare(strict_types=1);

namespace App\People\Application\Command\CreateHousehold;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Domain\Household;
use App\People\Domain\Repository\HouseholdRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateHouseholdHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private HouseholdRepository $households,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new household id */
    public function handle(CreateHouseholdCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $now = $this->clock->now();
        $household = Household::create($this->ids->uuid(), $command->name, $now);
        $this->households->insert($household);
        $this->audit->audit($command->actorId, 'household.created', $household->id, $now);

        return $household->id;
    }
}
