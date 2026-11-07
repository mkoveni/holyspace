<?php

declare(strict_types=1);

namespace App\Events\Application\Command\UpdateEventVenue;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdateEventVenueCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $venueId,
        public int $expectedVersion,
        public string $name,
        public string $address,
        public bool $active,
    ) {
    }
}
