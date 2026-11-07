<?php

declare(strict_types=1);

namespace App\Giving\Domain\Repository;

use App\Giving\Domain\OnlineRefund;
use App\Giving\Domain\PledgeAssignment;
use App\Shared\Domain\CalendarDate;

/**
 * Trusted Payments evidence for online gifts (`giving_payment_sources`, `giving_payment_refunds`): insert-only. Integrity
 * violations (unique charge and references, the refund guard and online-source triggers) become an evidence conflict.
 */
interface OnlineGivingRepository
{
    /** Records the provider charge behind an online gift, then checks the online-source trigger at once instead of at commit. */
    public function source(string $giftId, string $chargeId, string $reference): void;

    /**
     * Whether this refund id was already recorded with exactly these details. A refund id reused for other details is a
     * conflict.
     */
    public function refundExists(string $id, string $giftId, int $amountMinor, string $reference, CalendarDate $refundedOn): bool;

    public function refund(OnlineRefund $refund): void;

    /**
     * Active pledge assignments of the gift, ordered by pledge id, then id. Not locked: callers hold the gift row lock,
     * which every assignment write takes first.
     *
     * @return list<PledgeAssignment>
     */
    public function activeAssignments(string $giftId): array;
}
