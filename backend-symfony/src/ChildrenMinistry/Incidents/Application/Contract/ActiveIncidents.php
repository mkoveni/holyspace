<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Contract;

use App\Shared\Domain\CalendarDate;

/** The incidents still needing follow-up, as metadata only, for the ministry overview. Never a description, action or note. */
interface ActiveIncidents
{
    /** Active incidents within retention by review date, with the overdue and unassigned totals of all of them. */
    public function top(CalendarDate $on, int $limit): IncidentPage;
}
