<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Contract;

use App\Shared\Domain\CalendarDate;

/** How many incidents of a session still need follow-up. Counts only: no incident can be identified from them. */
interface SessionIncidentCounts
{
    /** Incidents of the session that are not closed and still within retention on the day. */
    public function unresolved(string $sessionId, CalendarDate $on): int;

    public function counts(string $sessionId, CalendarDate $on): IncidentCountSummary;
}
