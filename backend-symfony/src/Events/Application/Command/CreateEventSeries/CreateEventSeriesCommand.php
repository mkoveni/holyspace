<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CreateEventSeries;

use App\Events\Application\ScheduleDraft;
use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateEventSeriesCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $eventId,
        public int $expectedVersion,
        public ScheduleDraft $schedule,
        public ?string $previewHash,
    ) {
    }
}
