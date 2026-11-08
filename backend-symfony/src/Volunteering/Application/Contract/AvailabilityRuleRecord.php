<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use App\Volunteering\Domain\AvailabilityRule;

/** Java's `AvailabilityRule` record: a stored weekly rule of one availability revision. */
final readonly class AvailabilityRuleRecord
{
    public function __construct(public string $id, public string $volunteerId, public int $revision, public AvailabilityRule $rule)
    {
    }
}
