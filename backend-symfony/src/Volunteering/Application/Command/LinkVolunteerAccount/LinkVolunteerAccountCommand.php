<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\LinkVolunteerAccount;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class LinkVolunteerAccountCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $volunteerId,
        public int $expectedVersion,
        public ?string $accountId,
    ) {
    }
}
