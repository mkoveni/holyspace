<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Contract;

/** Whether a staff member may act on a session's incidents: they must be present on it. Owned here, implemented by Sessions. */
interface IncidentSessionScope
{
    /** @throws \App\ChildrenMinistry\Shared\Domain\ChildrenException when access is not allowed */
    public function require(string $actorId, string $sessionId): void;
}
