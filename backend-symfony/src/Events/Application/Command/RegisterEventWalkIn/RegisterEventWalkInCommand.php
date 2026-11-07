<?php

declare(strict_types=1);

namespace App\Events\Application\Command\RegisterEventWalkIn;

use App\Events\Application\WalkIn;
use App\Shared\Application\Command;

/** @implements Command<WalkIn> */
final readonly class RegisterEventWalkInCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $occurrenceId,
        public string $personId,
        public ?int $expectedVersion,
    ) {
    }
}
