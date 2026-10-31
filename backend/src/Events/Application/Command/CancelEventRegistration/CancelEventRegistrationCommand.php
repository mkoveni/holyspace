<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CancelEventRegistration;

use App\Shared\Application\CQRS\Command;

final readonly class CancelEventRegistrationCommand implements Command
{
    public function __construct(
        public string $eventId,
        public string $occurrenceId,
        public string $id,
        public string $reason,
        public int $expectedVersion,
    ) {
    }
}
