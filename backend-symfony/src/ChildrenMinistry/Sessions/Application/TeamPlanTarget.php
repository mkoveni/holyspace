<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

final readonly class TeamPlanTarget
{
    public function __construct(public string $sessionId, public int $version)
    {
    }
}
