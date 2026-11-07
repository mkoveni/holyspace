<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetFamilyArrivalReference;

final readonly class GetFamilyArrivalReferenceQuery
{
    public function __construct(public string $actorId, public string $sessionId, public string $childId)
    {
    }
}
