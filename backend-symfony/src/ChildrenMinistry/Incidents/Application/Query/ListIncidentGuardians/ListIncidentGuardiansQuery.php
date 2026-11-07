<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Query\ListIncidentGuardians;

final readonly class ListIncidentGuardiansQuery
{
    public function __construct(public string $incidentId)
    {
    }
}
