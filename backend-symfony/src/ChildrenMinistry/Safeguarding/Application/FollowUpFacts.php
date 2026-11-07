<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

final readonly class FollowUpFacts
{
    /**
     * @param list<FollowUpChild>  $children
     * @param list<FollowUpPickup> $pickups
     * @param list<FollowUpCare>   $care
     */
    public function __construct(public array $children, public array $pickups, public array $care)
    {
    }
}
