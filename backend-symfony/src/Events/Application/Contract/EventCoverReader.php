<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use App\Shared\Domain\CoverSlot;

interface EventCoverReader
{
    /** The event's cover pointer, or the empty slot (version 0) when one was never set. */
    public function cover(string $eventId): CoverSlot;
}
