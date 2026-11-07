<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListDonorAccess;

final readonly class ListDonorAccessQuery
{
    public function __construct(public int $limit, public int $offset)
    {
    }
}
