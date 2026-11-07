<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

interface MemberOfferRepository
{
    /** Publishes or withdraws an event for the member catalogue. */
    public function save(string $eventId, bool $enabled, int $version): void;
}
