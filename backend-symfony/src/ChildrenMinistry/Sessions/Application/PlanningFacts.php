<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\SessionStaff;
use App\ChildrenMinistry\Sessions\Domain\Worker;

final readonly class PlanningFacts
{
    /**
     * @param list<SessionStaff>    $staff    assignments of the selected sessions
     * @param list<Worker>          $workers  registered workers among those assigned or proposed
     * @param list<PlanningBooking> $bookings those workers' assignments to any open session overlapping the selection
     */
    public function __construct(public array $staff, public array $workers, public array $bookings)
    {
    }
}
