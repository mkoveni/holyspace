<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureRegistration;

use App\Shared\Application\CQRS\Command;

final readonly class ConfigureRegistrationCommand implements Command
{
    public function __construct(
        public string $eventId,
        public string $occurrenceId,
        public bool $open,
        public ?int $capacity,
        public bool $waitlistEnabled,
        public int $expectedVersion,
    ) {
    }
}
