<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetPledge;

final readonly class GetPledgeQuery
{
    public function __construct(
        public string $pledgeId,
        public int $limit,
        public int $offset,
    ) {
    }
}
