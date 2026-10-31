<?php

declare(strict_types=1);

namespace App\Events\Application\Command;

use App\Shared\Application\CQRS\Command;

final readonly class UpdateOccurrence implements Command
{
    public function __construct(
        public string $eventId,
        public string $occurrenceId,
        public string $startsAt,
        public string $endsAt,
        public string $location,
        public string $status,
    ) {
    }
}
