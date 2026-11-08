<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

final readonly class DonorPaymentPage
{
    /** @param list<DonorPayment> $items */
    public function __construct(public array $items, public int $total)
    {
    }
}
