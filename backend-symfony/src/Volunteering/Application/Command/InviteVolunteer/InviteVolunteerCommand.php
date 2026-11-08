<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\InviteVolunteer;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<string> */
final readonly class InviteVolunteerCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $assignmentId,
        public int $expectedVersion,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
