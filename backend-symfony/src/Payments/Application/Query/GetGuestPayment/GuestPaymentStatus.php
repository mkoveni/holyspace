<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetGuestPayment;

/** What a public giver may see of their own payment: never another person's data, never provider identifiers. */
final readonly class GuestPaymentStatus
{
    /** @param string $state AWAITING_PAYMENT, PAID, FAILED or TEST_PAYMENT */
    public function __construct(
        public string $orderId,
        public string $state,
        public int $amountMinor,
        public bool $confirmationQueued,
    ) {
    }
}
