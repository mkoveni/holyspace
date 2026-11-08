<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RespondMyVolunteerInvitation;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RespondMyVolunteerInvitationCommand implements Command
{
    public function __construct(
        public string $invitationId,
        public int $expectedVersion,
        public ?string $decision,
    ) {
    }
}
