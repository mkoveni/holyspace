<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListStationSessions;

use DateTimeImmutable;

final readonly class ListStationSessionsQuery
{
    public function __construct(public string $actorId, public ?DateTimeImmutable $from, public ?DateTimeImmutable $until, public int $limit, public int $offset)
    {
    }
}
