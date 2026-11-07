<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventCloseoutHistory;

final readonly class GetEventCloseoutHistoryQuery
{
    public function __construct(public string $occurrenceId, public int $limit, public int $offset)
    {
    }
}
