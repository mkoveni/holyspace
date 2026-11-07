<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListSessions;

use DateTimeImmutable;

final readonly class ListSessionsQuery
{
    public function __construct(public ?string $locationId, public ?DateTimeImmutable $from, public ?DateTimeImmutable $until, public int $limit, public int $offset)
    {
    }
}
