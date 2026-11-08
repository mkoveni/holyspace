<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Java `ServingViews.Profile`: the profile, its credentials (no evidence) and the current availability revision. */
final readonly class VolunteerProfile
{
    /**
     * @param list<ProfileCredential>           $credentials
     * @param list<AvailabilityRuleRecord>      $weekly
     * @param list<AvailabilityExceptionRecord> $exceptions
     */
    public function __construct(public VolunteerSummary $person, public array $credentials, public array $weekly, public array $exceptions)
    {
    }
}
