<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\ImportServingStaff;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ImportServingStaffCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $sessionId,
        public int $expectedVersion,
        public string $assignmentId,
        public string $responsibility,
        public bool $present,
    ) {
    }
}
