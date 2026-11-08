<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Java `ServingViews.Person`: a profile with the person's display name. */
final readonly class VolunteerSummary
{
    public function __construct(public VolunteerView $volunteer, public string $name)
    {
    }
}
