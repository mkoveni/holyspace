<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application\Query\ListIncidents;

final readonly class ListIncidentsQuery
{
    public function __construct(public ?string $sessionId, public ?string $childId, public string $status, public bool $overdueOnly, public int $limit, public int $offset)
    {
    }
}
