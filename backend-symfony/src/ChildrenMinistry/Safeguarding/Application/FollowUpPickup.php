<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

use DateTimeImmutable;

final readonly class FollowUpPickup
{
    public function __construct(public string $childId, public string $programmeId, public string $collectorId, public DateTimeImmutable $until)
    {
    }
}
