<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetMyGiving;

use App\Giving\Application\Contract\GiftSummary;
use App\Giving\Application\Contract\OnlineFund;
use App\Giving\Application\Contract\VerifiedDonor;
use App\Payments\Application\Contract\DonorPaymentPage;
use App\Payments\Application\Contract\PaymentProvider;

/** The donor's self-service overview; everything is empty when the account has no verified donor link. */
final readonly class MyGiving
{
    /**
     * @param list<OnlineFund>      $funds
     * @param list<PaymentProvider> $providers
     * @param list<GiftSummary>     $gifts
     */
    public function __construct(
        public ?VerifiedDonor $donor,
        public array $funds,
        public array $providers,
        public DonorPaymentPage $payments,
        public array $gifts,
        public int $giftTotal,
    ) {
    }
}
