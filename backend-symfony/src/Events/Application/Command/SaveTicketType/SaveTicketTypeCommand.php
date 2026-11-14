<?php

declare(strict_types=1);

namespace App\Events\Application\Command\SaveTicketType;

use App\Shared\Application\Command;

/** @implements Command<string> the ticket type id */
final readonly class SaveTicketTypeCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $occurrenceId,
        public ?string $id,
        public int $expectedVersion,
        public string $name,
        public string $description,
        public int $priceMinor,
        public ?int $quantityCap,
        public int $sortOrder,
        public bool $active,
    ) {
    }
}
