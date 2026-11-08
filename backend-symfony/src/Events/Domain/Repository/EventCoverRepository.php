<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Shared\Domain\CoverSlot;
use DateTimeImmutable;

interface EventCoverRepository
{
    /** Points the event at its new cover (or none); the caller holds the event lock and checked the version. */
    public function save(string $eventId, CoverSlot $slot, DateTimeImmutable $at): void;
}
