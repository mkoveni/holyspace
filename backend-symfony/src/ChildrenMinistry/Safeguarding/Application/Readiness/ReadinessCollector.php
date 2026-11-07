<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Readiness;

final readonly class ReadinessCollector
{
    public function __construct(public string $childId, public string $authorityId, public string $collectorId)
    {
    }
}
