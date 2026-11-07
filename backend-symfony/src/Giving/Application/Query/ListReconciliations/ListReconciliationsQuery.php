<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListReconciliations;

final readonly class ListReconciliationsQuery
{
    public function __construct(public int $limit, public int $offset)
    {
    }
}
