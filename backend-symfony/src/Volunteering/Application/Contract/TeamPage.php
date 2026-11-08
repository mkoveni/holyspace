<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Java `ServingViews.Teams`. */
final readonly class TeamPage
{
    /** @param list<TeamRecord> $teams */
    public function __construct(public array $teams, public int $total)
    {
    }
}
