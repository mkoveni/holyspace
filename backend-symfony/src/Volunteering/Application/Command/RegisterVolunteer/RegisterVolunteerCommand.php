<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RegisterVolunteer;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class RegisterVolunteerCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?string $personId,
        public ?string $accountId,
    ) {
    }
}
