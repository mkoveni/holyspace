<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListChurchEvents;

final readonly class ListChurchEventsQuery
{
    public function __construct(
        public string $search,
        public bool $includeArchived,
        public int $limit,
        public int $offset,
    ) {
    }
}
