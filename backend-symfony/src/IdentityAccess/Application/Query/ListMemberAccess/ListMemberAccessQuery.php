<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListMemberAccess;

final readonly class ListMemberAccessQuery
{
    public function __construct(
        public int $limit,
        public int $offset,
    ) {
    }
}
