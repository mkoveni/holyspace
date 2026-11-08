<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetDonorPayment;

final readonly class GetDonorPaymentQuery
{
    public function __construct(public string $orderId)
    {
    }
}
