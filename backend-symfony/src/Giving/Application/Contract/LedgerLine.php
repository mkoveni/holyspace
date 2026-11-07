<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Shared\Domain\CalendarDate;

/**
 * One allocation line of the ledger (Java GivingLedgerReader.Line): a gift (`GIFT`, positive), a full reversal
 * (`REVERSAL`, negative, dated by the reversal) or a provider refund (`REFUND`, negative, dated by the refund).
 */
final readonly class LedgerLine
{
    public function __construct(
        public string $giftId,
        public string $kind,
        public CalendarDate $date,
        public ?string $donorId,
        public string $donorName,
        public string $fundId,
        public string $fundName,
        public string $currency,
        public int $amountMinor,
        public string $method,
        public ?string $reference,
        public string $reason,
    ) {
    }
}
