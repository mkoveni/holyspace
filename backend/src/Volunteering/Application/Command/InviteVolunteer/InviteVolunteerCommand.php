<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\InviteVolunteer;

use App\Shared\Application\CQRS\Command;

final readonly class InviteVolunteerCommand implements Command
{
    public function __construct(
        public string $shiftId,
        public string $personId,
        public string $expiresAt,
    ) {
    }
}
