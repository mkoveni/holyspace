<?php

declare(strict_types=1);

namespace App\People\Application\Query\SearchPeople;

use App\Shared\Application\CQRS\Query;

final readonly class SearchPeopleQuery implements Query
{
    public function __construct(
        public string $term = '',
        public int $limit = 20,
        public array $excludeIds = [],
        public array $ids = [],
    ) {
    }
}
