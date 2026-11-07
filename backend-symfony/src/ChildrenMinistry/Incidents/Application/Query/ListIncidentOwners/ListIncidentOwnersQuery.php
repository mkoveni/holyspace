<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Query\ListIncidentOwners;

final readonly class ListIncidentOwnersQuery
{
    public function __construct(public string $search)
    {
    }
}
