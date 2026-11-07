<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

/** Everything recorded about who may act for, consent for, and collect one child. */
final readonly class SafeguardingView
{
    /**
     * @param list<GuardianAuthorityView>   $authorities
     * @param list<ConsentView>             $consents
     * @param list<PickupAuthorizationView> $pickupAuthorizations
     */
    public function __construct(public array $authorities, public array $consents, public array $pickupAuthorizations)
    {
    }
}
