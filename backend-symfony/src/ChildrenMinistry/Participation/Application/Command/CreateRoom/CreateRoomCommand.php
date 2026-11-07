<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\CreateRoom;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateRoomCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $locationId,
        public string $name,
        public int $capacity,
    ) {
    }
}
