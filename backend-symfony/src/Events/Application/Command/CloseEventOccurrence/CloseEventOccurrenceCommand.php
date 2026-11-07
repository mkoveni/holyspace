<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CloseEventOccurrence;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class CloseEventOccurrenceCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $occurrenceId,
        public int $expectedVersion,
        public ?string $reviewHash,
        public string $note,
        public string $followUp,
        public bool $attendanceReviewed,
        public bool $followUpReviewed,
    ) {
    }
}
