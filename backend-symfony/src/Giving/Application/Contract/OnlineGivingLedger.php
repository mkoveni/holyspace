<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Shared\Domain\CalendarDate;

/**
 * Trusted Payments integration only, called after durable provider verification. It is never exposed over HTTP and
 * never accepts a user's payment assertion. Each call runs in the caller's transaction when there is one (otherwise in
 * its own); callers take DonorIdentityGuard before calling, so the donor link lock precedes the People and fund locks.
 */
interface OnlineGivingLedger
{
    /**
     * Locks the donor person row, then the fund, before a checkout starts. 400 "Choose an available donor." or 409
     * "Choose an active giving fund.".
     */
    public function validate(string $donorId, string $fundId): OnlineDonor;

    /**
     * Posts a verified ZAR charge as an ONLINE gift to one fund. Idempotent on the charge id, which becomes the gift id: an
     * identical retry returns it without writing, the same id with other details is a 409.
     *
     * @return string the gift id (the charge id)
     */
    public function post(
        string $chargeId,
        string $donorId,
        string $fundId,
        int $amountMinor,
        string $reference,
        CalendarDate $receivedOn,
        string $initiatedBy,
    ): string;

    /**
     * Records a provider-completed refund of an online gift and cancels the gift's active pledge assignments for review.
     * Idempotent on the refund id: an identical retry writes nothing, the same id with other details is a 409.
     */
    public function refund(
        string $refundId,
        string $giftId,
        int $amountMinor,
        string $reference,
        CalendarDate $refundedOn,
        string $initiatedBy,
    ): void;
}
