<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use App\ChildrenMinistry\Sessions\Application\IncidentCounts;
use App\Shared\Domain\CalendarDate;

/** Sessions ask Incidents how many follow-ups remain open. */
interface SessionIncidents
{
    /** Incidents of the session that are not closed and are still within retention on the day. */
    public function unresolved(string $sessionId, CalendarDate $on): int;

    /** Open incidents of the session, how many are due for review, and how many have no owner. */
    public function counts(string $sessionId, CalendarDate $on): IncidentCounts;
}
