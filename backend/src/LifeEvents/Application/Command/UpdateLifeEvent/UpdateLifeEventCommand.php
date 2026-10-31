<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Command\UpdateLifeEvent;

use App\Shared\Application\CQRS\Command;

final readonly class UpdateLifeEventCommand implements Command
{
    public function __construct(
        public string $lifeEventId,
        public string $type,
        public array $participants,
        public string $eventDate,
        public array $details = [],
        public ?string $notes = null,
    ) {
    }
}
