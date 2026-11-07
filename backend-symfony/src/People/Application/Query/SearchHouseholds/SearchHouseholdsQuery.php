<?php

declare(strict_types=1);

namespace App\People\Application\Query\SearchHouseholds;

final readonly class SearchHouseholdsQuery
{
    public function __construct(
        public string $search,
        public int $limit,
        public int $offset,
    ) {
    }
}
