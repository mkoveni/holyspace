<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSummary;
use App\ChildrenMinistry\Incidents\Domain\IncidentDetails;
use App\ChildrenMinistry\Incidents\Domain\IncidentEvent;

/** Restricted: the private details and history of one incident. Only produced by an audited access. */
final readonly class IncidentAccessView
{
    /** @param list<IncidentEvent> $events newest first */
    public function __construct(public IncidentSummary $incident, public IncidentDetails $details, public array $events, public int $totalEvents)
    {
    }

    public function __debugInfo(): array
    {
        return ['restricted' => true];
    }
}
