<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentReader;
use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSummary;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;

/** An incident past its retention date no longer exists as far as anyone but the purge is concerned. */
final readonly class IncidentAccess
{
    public function __construct(private IncidentReader $reader)
    {
    }

    public function current(string $id, CalendarDate $on): IncidentSummary
    {
        $incident = $this->reader->find($id);
        if (null === $incident || $incident->retainUntil->isBefore($on)) {
            throw ChildrenException::missing('Current incident');
        }

        return $incident;
    }
}
