<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Shared\Domain\CalendarDate;

/** A gift in a batch with its current reversal and refund state (Java `ReconciliationReader.Item`). */
final readonly class ReconciliationItem
{
    public function __construct(
        public string $giftId,
        public string $donorName,
        public CalendarDate $receivedOn,
        public int $amountMinor,
        public bool $reversed,
        public int $refundedMinor,
    ) {
    }

    /** What this gift no longer contributes to the batch: all of it when reversed, else its provider refunds. */
    public function reversedMinor(): int
    {
        return $this->reversed ? $this->amountMinor : $this->refundedMinor;
    }
}
