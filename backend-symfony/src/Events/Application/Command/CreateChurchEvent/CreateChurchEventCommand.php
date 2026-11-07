<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CreateChurchEvent;

use App\Events\Application\ScheduleDraft;
use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateChurchEventCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $title,
        public string $type,
        public string $description,
        public ScheduleDraft $schedule,
        public ?string $previewHash,
    ) {
    }
}
