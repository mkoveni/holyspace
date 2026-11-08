<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\PaymentException;
use App\Payments\Domain\Refund;

final readonly class PaymentDetail
{
    /**
     * @param list<ChargeView>     $charges
     * @param list<Refund>         $refunds
     * @param list<PaymentHistory> $history newest first, at most 100
     */
    public function __construct(public PaymentSummary $order, public array $charges, public array $refunds, public array $history)
    {
    }

    public function charge(string $id): ChargeView
    {
        foreach ($this->charges as $charge) {
            if ($charge->charge->id === $id) {
                return $charge;
            }
        }
        throw PaymentException::missing();
    }

    /** Whether any refund is unmatched or belongs to the charge (a confirmation then cannot be sent). */
    public function refundTouches(string $chargeId): bool
    {
        foreach ($this->refunds as $refund) {
            if (null === $refund->chargeId || $refund->chargeId === $chargeId) {
                return true;
            }
        }

        return false;
    }
}
