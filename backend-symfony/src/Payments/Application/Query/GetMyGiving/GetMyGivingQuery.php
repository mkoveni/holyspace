<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetMyGiving;

final readonly class GetMyGivingQuery
{
    public function __construct(public int $limit, public int $paymentOffset, public int $giftOffset)
    {
    }
}
