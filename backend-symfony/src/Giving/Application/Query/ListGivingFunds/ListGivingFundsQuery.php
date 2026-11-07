<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListGivingFunds;

final readonly class ListGivingFundsQuery
{
    public function __construct(
        public bool $archived,
        public ?string $search,
        public int $limit,
        public int $offset,
    ) {
    }
}
