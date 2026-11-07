<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use DateTimeImmutable;

/** A registered worker as a planner sees them: no evidence reference and no account id. */
final readonly class PlanningWorkerOption
{
    public function __construct(public string $id, public string $name, public DateTimeImmutable $screenedUntil, public DateTimeImmutable $trainedUntil, public bool $revoked)
    {
    }
}
