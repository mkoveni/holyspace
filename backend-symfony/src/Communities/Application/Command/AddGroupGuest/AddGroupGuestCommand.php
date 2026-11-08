<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\AddGroupGuest;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class AddGroupGuestCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $meetingId,
        public int $expectedVersion,
        public string $personId,
        public ?string $reason,
    ) {
    }
}
