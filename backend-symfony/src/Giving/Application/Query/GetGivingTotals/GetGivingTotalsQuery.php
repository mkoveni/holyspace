<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGivingTotals;

use App\Giving\Application\Contract\GiftFilter;

final readonly class GetGivingTotalsQuery
{
    public function __construct(public GiftFilter $filter)
    {
    }
}
