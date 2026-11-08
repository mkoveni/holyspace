<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\People\Application\Contract\PersonDirectory;
use App\Volunteering\Application\Contract\VolunteerSummary;
use App\Volunteering\Application\Contract\VolunteerView;

/** Java `ServingViews.person`: a profile with its People display name. */
final readonly class VolunteerNames
{
    public function __construct(private PersonDirectory $people)
    {
    }

    public function summary(VolunteerView $volunteer): VolunteerSummary
    {
        return new VolunteerSummary($volunteer, $this->people->get($volunteer->personId)->name ?? 'Unavailable person');
    }
}
