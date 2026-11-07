<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Domain\Repository;

use App\ChildrenMinistry\Incidents\Domain\Incident;
use App\ChildrenMinistry\Incidents\Domain\IncidentDetails;
use App\ChildrenMinistry\Incidents\Domain\IncidentEvent;
use App\Shared\Domain\CalendarDate;

/** Write side of incidents. */
interface IncidentRepository
{
    public function lock(string $id): ?Incident;

    public function insert(Incident $incident, IncidentDetails $details, string $requestId, string $requestHash): void;

    /** Updates the row only if its stored version is one less than the incident's; otherwise a 409. */
    public function update(Incident $incident): void;

    public function append(IncidentEvent $event): void;

    /**
     * Deletes the private details and blanks the private event notes of incidents whose retention ended before the day,
     * keeping incident and event metadata.
     *
     * @return int how many incidents lost their details
     */
    public function purgeExpired(CalendarDate $on, int $limit): int;
}
