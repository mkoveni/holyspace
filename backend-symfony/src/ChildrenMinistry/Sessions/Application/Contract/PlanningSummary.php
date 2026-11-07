<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

final readonly class PlanningSummary
{
    public function __construct(public int $sessions, public int $ready, public int $attention, public int $missingLeaders, public int $shortCapacity, public int $clashes)
    {
    }
}
