<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Shared\Domain\CalendarDate;

/** A charge as its donor sees it; the confirmation is the frozen message, without recipient or approval evidence. */
final readonly class DonorCharge
{
    public function __construct(
        public string $id,
        public int $amountMinor,
        public int $refundedMinor,
        public CalendarDate $receivedOn,
        public string $givingStatus,
        public string $confirmationStatus,
        public bool $captured,
        public ?DonorConfirmation $confirmation,
    ) {
    }
}
