<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventVenues;

final readonly class ListEventVenuesQuery
{
    public function __construct(
        public string $search,
        public bool $includeArchived,
        public int $limit,
        public int $offset,
    ) {
    }
}
