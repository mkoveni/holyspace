<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetGift;

final readonly class GetGiftQuery
{
    public function __construct(public string $giftId)
    {
    }
}
