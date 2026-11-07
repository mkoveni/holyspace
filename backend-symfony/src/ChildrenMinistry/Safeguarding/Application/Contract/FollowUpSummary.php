<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

final readonly class FollowUpSummary
{
    public function __construct(public int $children, public int $missing, public int $expired, public int $expiring, public int $careDue)
    {
    }
}
