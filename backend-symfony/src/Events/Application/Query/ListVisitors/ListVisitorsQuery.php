<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListVisitors;

final readonly class ListVisitorsQuery
{
    public function __construct(public string $occurrenceId, public ?string $reviewStatus, public int $limit, public int $offset)
    {
    }
}
