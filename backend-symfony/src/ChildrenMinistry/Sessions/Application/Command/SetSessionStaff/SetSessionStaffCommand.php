<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\SetSessionStaff;

use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class SetSessionStaffCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $sessionId,
        public int $expectedVersion,
        public string $workerId,
        public StaffRole $role,
        public bool $present,
    ) {
    }
}
