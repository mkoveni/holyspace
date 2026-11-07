<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Shared\Domain\CalendarDate;

/** One row of the gift list; the donor name is "Anonymous" or "Person unavailable" when there is none to show. */
final readonly class GiftSummary
{
    public function __construct(
        public string $id,
        public ?string $donorId,
        public string $donorName,
        public int $amountMinor,
        public string $currency,
        public CalendarDate $receivedOn,
        public string $method,
        public ?string $externalReference,
        public bool $reversed,
        public bool $reconciled,
        public int $refundedMinor,
    ) {
    }
}
