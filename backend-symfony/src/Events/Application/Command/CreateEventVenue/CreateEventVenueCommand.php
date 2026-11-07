<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CreateEventVenue;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateEventVenueCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $name,
        public string $address,
        public bool $exclusive,
    ) {
    }
}
