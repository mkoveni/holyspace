<?php

declare(strict_types=1);

namespace App\Events\Application\Command;

use App\Shared\Application\CQRS\Command;

final readonly class CreateEvent implements Command
{
    public function __construct(
        public string $title,
        public string $type,
        public ?string $description,
        public string $timezone,
        public string $startsAt,
        public string $endsAt,
        public string $location,
        public string $recurrence,
        public int $occurrenceCount,
    ) {
    }
}
