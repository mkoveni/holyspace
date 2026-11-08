<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

final readonly class DonorPaymentDetail
{
    /**
     * @param list<DonorCharge> $charges
     * @param list<DonorRefund> $refunds
     */
    public function __construct(public DonorPayment $order, public array $charges, public array $refunds)
    {
    }
}
