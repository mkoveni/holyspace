<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Payments\Domain\Charge;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\Refund;

/**
 * What a payment order is for decides where its verified money lands. Payments owns the evidence; each purpose owns its
 * consequence (a gift in Giving, tickets in Events). Every method runs inside PaymentPosting's own transaction, after the
 * payment order row is locked, and must be idempotent on the charge or refund id.
 */
interface PurposeSettlement
{
    /** The `payments_orders.purpose` value this settlement handles. */
    public function purpose(): string;

    /** Applies a verified live charge. Returns the id recorded on the charge as its settlement (the Giving gift id for giving). */
    public function settle(PaymentOrder $order, Charge $charge): string;

    /** Applies a verified refund of a settled live charge. */
    public function refund(PaymentOrder $order, Charge $charge, Refund $refund): void;

    /** A sandbox (test mode) order never settles money: release whatever the purpose reserved for it. */
    public function release(PaymentOrder $order): void;
}
