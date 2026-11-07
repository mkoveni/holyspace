<?php

declare(strict_types=1);

namespace App\Events\Application\Command\RegisterEventPerson;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class RegisterEventPersonCommand implements Command
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
