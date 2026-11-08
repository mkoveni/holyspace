<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RespondVolunteerInvitation;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RespondVolunteerInvitationCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $invitationId,
        public int $expectedVersion,
        public ?string $decision,
    ) {
    }
}
