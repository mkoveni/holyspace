<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListPledgeGiftCandidates;

final readonly class ListPledgeGiftCandidatesQuery
{
    public function __construct(
        public string $pledgeId,
        public int $limit,
        public int $offset,
    ) {
    }
}
