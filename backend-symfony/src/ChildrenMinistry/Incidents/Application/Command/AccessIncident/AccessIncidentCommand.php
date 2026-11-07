<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Command\AccessIncident;

use App\ChildrenMinistry\Incidents\Application\IncidentAccessView;
use App\Shared\Application\Command;

/** @implements Command<IncidentAccessView> */
final readonly class AccessIncidentCommand implements Command
{
    public function __construct(public string $actorId, public string $incidentId, public int $limit, public int $offset)
    {
    }
}
