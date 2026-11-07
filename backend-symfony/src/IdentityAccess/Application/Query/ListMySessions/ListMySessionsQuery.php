<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListMySessions;

final readonly class ListMySessionsQuery
{
    public function __construct(
        public int $limit,
        public int $offset,
    ) {
    }
}
