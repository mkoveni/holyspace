<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetPickupCollectors;

final readonly class GetPickupCollectorsQuery
{
    public function __construct(public string $actorId, public string $attendanceId)
    {
    }
}
