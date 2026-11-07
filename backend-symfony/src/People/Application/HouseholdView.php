<?php

declare(strict_types=1);

namespace App\People\Application;

final readonly class HouseholdView
{
    /** @param list<MembershipView> $memberships */
    public function __construct(public HouseholdSummary $household, public array $memberships)
    {
    }
}
