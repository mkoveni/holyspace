<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class CalendarPreview
{
    /** @param list<CalendarOccurrence> $dates */
    public function __construct(public string $hash, public array $dates)
    {
    }
}
