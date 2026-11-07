<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\CreateLocation;

use App\ChildrenMinistry\Participation\Domain\Location;
use App\ChildrenMinistry\Participation\Domain\Repository\VenueRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateLocationHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private VenueRepository $venues,
    ) {
    }

    /** @return string the new location id */
    public function handle(CreateLocationCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');

        $now = $this->clock->now();
        $location = Location::create($this->ids->uuid(), $command->name, $command->timeZone);
        $this->venues->insertLocation($location);
        $this->audit->record($command->actorId, 'location.created', $location->id, '', $now);

        return $location->id;
    }
}
