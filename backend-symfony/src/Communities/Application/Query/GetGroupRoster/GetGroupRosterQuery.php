<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetGroupRoster;

final readonly class GetGroupRosterQuery
{
    public function __construct(public string $meetingId, public int $limit, public int $offset)
    {
    }
}
