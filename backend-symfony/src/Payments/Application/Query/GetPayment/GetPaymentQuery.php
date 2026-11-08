<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetPayment;

final readonly class GetPaymentQuery
{
    public function __construct(public string $orderId)
    {
    }
}
