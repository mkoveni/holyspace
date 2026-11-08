<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetServingTeam;

final readonly class GetServingTeamQuery
{
    public function __construct(public string $teamId)
    {
    }
}
