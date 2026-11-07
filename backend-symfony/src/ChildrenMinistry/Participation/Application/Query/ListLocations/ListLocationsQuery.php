<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListLocations;

final readonly class ListLocationsQuery
{
    public function __construct(
        public int $limit,
        public int $offset,
    ) {
    }
}
