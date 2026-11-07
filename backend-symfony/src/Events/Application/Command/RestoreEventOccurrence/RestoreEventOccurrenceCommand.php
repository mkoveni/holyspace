<?php

declare(strict_types=1);

namespace App\Events\Application\Command\RestoreEventOccurrence;

use App\Events\Application\OccurrenceChangeDraft;
use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RestoreEventOccurrenceCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?OccurrenceChangeDraft $draft,
        public ?string $reviewHash,
        public string $reason,
    ) {
    }
}
