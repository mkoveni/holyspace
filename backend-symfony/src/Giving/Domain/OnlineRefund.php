<?php

declare(strict_types=1);

namespace App\Giving\Domain;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/**
 * Immutable evidence of a provider-completed refund of part or all of an online gift (`giving_payment_refunds`). It is
 * the only correction an online gift accepts; the database rejects updates, deletes and refunds beyond the gift.
 */
final readonly class OnlineRefund
{
    private function __construct(
        public string $id,
        public string $giftId,
        public string $fundId,
        public int $amountMinor,
        public string $reference,
        public CalendarDate $refundedOn,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** Created only through Gift::refundOnline, which applies the amount and chronology rules. */
    public static function record(
        string $id,
        string $giftId,
        string $fundId,
        int $amountMinor,
        string $reference,
        CalendarDate $refundedOn,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $giftId, $fundId, $amountMinor, $reference, $refundedOn, $createdBy, $createdAt);
    }
}
