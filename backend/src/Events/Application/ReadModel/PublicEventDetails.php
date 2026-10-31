<?php

declare(strict_types=1);

namespace App\Events\Application\ReadModel;

interface PublicEventDetails
{
    /** Public display fields only; no people, registrations or internal notes. */
    public function get(string $eventId, string $occurrenceId): ?array;
}
