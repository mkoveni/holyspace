<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure\Persistence;

use App\ChildrenMinistry\Incidents\Application\Contract\SessionIncidentCounts;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionIncidents;
use App\ChildrenMinistry\Sessions\Application\IncidentCounts;
use App\Shared\Domain\CalendarDate;

/** Sessions ask the Incidents module, through its published counts, how many follow-ups remain. */
final readonly class PublishedSessionIncidents implements SessionIncidents
{
    public function __construct(private SessionIncidentCounts $incidents)
    {
    }

    public function unresolved(string $sessionId, CalendarDate $on): int
    {
        return $this->incidents->unresolved($sessionId, $on);
    }

    public function counts(string $sessionId, CalendarDate $on): IncidentCounts
    {
        $counts = $this->incidents->counts($sessionId, $on);

        return new IncidentCounts($counts->unresolved, $counts->overdue, $counts->unassigned);
    }
}
