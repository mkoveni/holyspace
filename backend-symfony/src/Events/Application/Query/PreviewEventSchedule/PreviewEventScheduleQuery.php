<?php

declare(strict_types=1);

namespace App\Events\Application\Query\PreviewEventSchedule;

use App\Events\Application\ScheduleDraft;

final readonly class PreviewEventScheduleQuery
{
    public function __construct(public ScheduleDraft $draft)
    {
    }
}
