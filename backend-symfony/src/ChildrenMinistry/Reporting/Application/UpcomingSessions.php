<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application;

final readonly class UpcomingSessions
{
    /** @param list<OverviewSession> $sessions the first five */
    public function __construct(public int $total, public array $sessions)
    {
    }
}
