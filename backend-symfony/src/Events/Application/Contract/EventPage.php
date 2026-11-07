<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class EventPage
{
    /** @param list<EventView> $events */
    public function __construct(public array $events, public int $total)
    {
    }
}
