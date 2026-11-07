<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class CalendarPage
{
    /** @param list<CalendarItem> $occurrences */
    public function __construct(public array $occurrences, public int $total)
    {
    }
}
