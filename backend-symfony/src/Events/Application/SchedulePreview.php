<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\VenueView;
use App\Events\Domain\ScheduleWindow;

final readonly class SchedulePreview
{
    /** @param list<ScheduleWindow> $dates */
    public function __construct(public string $hash, public VenueView $venue, public array $dates)
    {
    }
}
