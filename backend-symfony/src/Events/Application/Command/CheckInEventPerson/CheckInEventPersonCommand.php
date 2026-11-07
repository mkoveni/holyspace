<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CheckInEventPerson;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CheckInEventPersonCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $registrationId,
        public int $expectedVersion,
    ) {
    }
}
