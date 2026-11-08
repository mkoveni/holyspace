<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetDonorPayment;

use App\Giving\Application\Contract\DonorIdentity;
use App\Payments\Application\Contract\DonorPaymentDetail;
use App\Payments\Application\Contract\DonorPaymentReader;
use App\Payments\Domain\PaymentException;

/** The caller's own payment only; anyone else's (or no verified link) is missing. */
final readonly class GetDonorPaymentHandler
{
    public function __construct(private DonorIdentity $donors, private DonorPaymentReader $reader)
    {
    }

    public function handle(GetDonorPaymentQuery $q): DonorPaymentDetail
    {
        $donor = $this->donors->current() ?? throw PaymentException::missing();

        return $this->reader->ownDetail($donor->id, $q->orderId) ?? throw PaymentException::missing();
    }
}
