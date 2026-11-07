<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CancelPledgeAssignment;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class CancelPledgeAssignmentCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $assignmentId,
        public int $expectedVersion,
        public ?string $reason,
    ) {
    }
}
