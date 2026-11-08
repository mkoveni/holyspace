<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListServingTeams;

final readonly class ListServingTeamsQuery
{
    public function __construct(public int $limit, public int $offset)
    {
    }
}
