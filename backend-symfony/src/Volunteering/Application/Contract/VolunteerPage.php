<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Java `ServingViews.Volunteers`. */
final readonly class VolunteerPage
{
    /** @param list<VolunteerSummary> $volunteers */
    public function __construct(public array $volunteers, public int $total)
    {
    }
}
