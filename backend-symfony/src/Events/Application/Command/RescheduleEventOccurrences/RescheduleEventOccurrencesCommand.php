<?php

declare(strict_types=1);

namespace App\Events\Application\Command\RescheduleEventOccurrences;

use App\Events\Application\OccurrenceChangeDraft;
use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RescheduleEventOccurrencesCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?OccurrenceChangeDraft $draft,
        public ?string $reviewHash,
        public string $reason,
    ) {
    }
}
