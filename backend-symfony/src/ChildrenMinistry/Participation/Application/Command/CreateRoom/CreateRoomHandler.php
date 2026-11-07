<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\CreateRoom;

use App\ChildrenMinistry\Participation\Application\Contract\VenueReader;
use App\ChildrenMinistry\Participation\Domain\Repository\VenueRepository;
use App\ChildrenMinistry\Participation\Domain\Room;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateRoomHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private VenueRepository $venues,
        private VenueReader $readModel,
    ) {
    }

    /** @return string the new room id */
    public function handle(CreateRoomCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');
        if (null === $this->readModel->location($command->locationId)) {
            throw ChildrenException::missing('Location');
        }

        $now = $this->clock->now();
        $room = Room::create($this->ids->uuid(), $command->locationId, $command->name, $command->capacity);
        $this->venues->insertRoom($room);
        $this->audit->record($command->actorId, 'room.created', $room->id, '', $now);

        return $room->id;
    }
}
