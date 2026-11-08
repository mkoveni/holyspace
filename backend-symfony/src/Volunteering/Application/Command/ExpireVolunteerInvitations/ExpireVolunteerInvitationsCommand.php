<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ExpireVolunteerInvitations;

use App\Shared\Application\Command;

/** @implements Command<int> */
final readonly class ExpireVolunteerInvitationsCommand implements Command
{
    public function __construct(
        public string $actorId,
        public int $limit,
    ) {
    }
}
