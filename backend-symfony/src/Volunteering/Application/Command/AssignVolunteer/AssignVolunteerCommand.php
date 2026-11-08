<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\AssignVolunteer;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class AssignVolunteerCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?string $requestId,
        public string $shiftId,
        public int $expectedVersion,
        public string $volunteerId,
        public ?string $status,
    ) {
    }
}
