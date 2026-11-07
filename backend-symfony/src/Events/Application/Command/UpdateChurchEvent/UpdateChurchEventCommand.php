<?php

declare(strict_types=1);

namespace App\Events\Application\Command\UpdateChurchEvent;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdateChurchEventCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $eventId,
        public int $expectedVersion,
        public string $title,
        public string $type,
        public string $description,
        public bool $active,
        public string $reason,
    ) {
    }
}
