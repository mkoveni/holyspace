<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListPledges;

final readonly class ListPledgesQuery
{
    public function __construct(
        public ?string $donorId,
        public ?string $status,
        public int $limit,
        public int $offset,
    ) {
    }
}
