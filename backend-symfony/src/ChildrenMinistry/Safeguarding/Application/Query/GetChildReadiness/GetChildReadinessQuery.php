<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Query\GetChildReadiness;

final readonly class GetChildReadinessQuery
{
    public function __construct(public string $programmeId, public string $childId)
    {
    }
}
