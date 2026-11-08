<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ChangeVolunteerAssignment;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ChangeVolunteerAssignmentCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $assignmentId,
        public int $expectedVersion,
        public ?string $status,
        public ?string $reason,
    ) {
    }
}
