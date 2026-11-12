<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetGuestPayment;

use App\Payments\Application\Contract\GuestAccess;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\Repository\GuestConfirmationRepository;
use App\Payments\Domain\Repository\PaymentRepository;

/** Another giver's order, a wrong token and an unknown order are all the same 404. */
final readonly class GetGuestPaymentHandler
{
    public function __construct(
        private PaymentRepository $payments,
        private GuestConfirmationRepository $confirmations,
        private GuestAccess $access,
    ) {
    }

    public function handle(GetGuestPaymentQuery $q): GuestPaymentStatus
    {
        $order = $this->payments->findByGuestAccess($this->access->hash($q->accessToken)) ?? throw PaymentException::missing();
        $queued = false;
        foreach ($this->payments->charges($order->id) as $charge) {
            $queued = $queued || null !== $this->confirmations->forCharge($charge->id);
        }
        $state = match (true) {
            'FAILED' === $order->status => 'FAILED',
            'PAID' === $order->status && !$order->isLive() => 'TEST_PAYMENT',
            'PAID' === $order->status => 'PAID',
            default => 'AWAITING_PAYMENT',
        };

        return new GuestPaymentStatus($order->id, $state, $order->amountMinor, $queued);
    }
}
