<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure;

use App\Giving\Application\Contract\OnlineGivingLedger;
use App\Payments\Application\Contract\PurposeSettlement;
use App\Payments\Application\PaymentPosting;
use App\Payments\Domain\Charge;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Refund;

/** Giving purpose: a verified charge is an ONLINE gift to the order's fund; a refund is recorded against that gift. */
final readonly class GivingSettlement implements PurposeSettlement
{
    public function __construct(private OnlineGivingLedger $giving)
    {
    }

    public function purpose(): string
    {
        return PaymentOrder::GIVING;
    }

    public function settle(PaymentOrder $order, Charge $charge): string
    {
        return $this->giving->post(
            $charge->id,
            $order->donorId,
            $order->fundId ?? throw PaymentException::invalid('A giving payment needs a fund.'),
            $charge->amountMinor,
            PaymentPosting::reference($order, $charge->reference),
            $charge->receivedOn,
            $order->createdBy
        );
    }

    public function refund(PaymentOrder $order, Charge $charge, Refund $refund): void
    {
        $this->giving->refund(
            $refund->id,
            $charge->giftId ?? throw PaymentException::conflict('Verified payment still needs ledger posting.'),
            $refund->amountMinor,
            PaymentPosting::reference($order, $refund->reference),
            $refund->refundedOn,
            $refund->createdBy ?? $order->createdBy
        );
    }

    public function release(PaymentOrder $order): void
    {
    }
}
