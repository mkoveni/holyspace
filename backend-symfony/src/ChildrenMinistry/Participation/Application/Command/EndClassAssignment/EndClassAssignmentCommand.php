<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\EndClassAssignment;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class EndClassAssignmentCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $assignmentId,
        public int $expectedVersion,
    ) {
    }
}
