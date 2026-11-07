<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureEventRegistration;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ConfigureEventRegistrationCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $occurrenceId,
        public int $expectedVersion,
        public bool $open,
        public ?int $capacity,
        public bool $waitlistEnabled,
        public string $reason,
    ) {
    }
}
