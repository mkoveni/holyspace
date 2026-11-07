<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\SetSessionStatus;

use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class SetSessionStatusCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $sessionId,
        public int $expectedVersion,
        public SessionStatus $status,
        public ?string $reason,
        public bool $acknowledgeIncidents = false,
    ) {
    }
}
