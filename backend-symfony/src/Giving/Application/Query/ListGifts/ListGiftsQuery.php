<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\ListGifts;

use App\Giving\Application\Contract\GiftFilter;

final readonly class ListGiftsQuery
{
    public function __construct(public GiftFilter $filter)
    {
    }
}
