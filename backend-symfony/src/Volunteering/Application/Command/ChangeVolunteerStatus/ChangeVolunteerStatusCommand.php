<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ChangeVolunteerStatus;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ChangeVolunteerStatusCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $volunteerId,
        public int $expectedVersion,
        public ?string $status,
    ) {
    }
}
