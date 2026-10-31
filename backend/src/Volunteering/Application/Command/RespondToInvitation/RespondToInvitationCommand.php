<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RespondToInvitation;

use App\Shared\Application\CQRS\Command;

final readonly class RespondToInvitationCommand implements Command
{
    public function __construct(
        public string $id,
        public string $decision,
        public int $expectedVersion,
    ) {
    }
}
