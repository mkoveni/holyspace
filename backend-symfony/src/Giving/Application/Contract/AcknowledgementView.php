<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Giving\Domain\AcknowledgementLine;
use App\Shared\Domain\CalendarDate;

/**
 * Printable acknowledgement evidence (Java AcknowledgementReader.View): the frozen issue snapshot plus the gift's current
 * void status. A provider refund takes precedence over a manual reversal for the void date and reason.
 */
final readonly class AcknowledgementView
{
    /** @param list<AcknowledgementLine> $lines ordered by fund name, then fund id */
    public function __construct(
        public string $id,
        public string $giftId,
        public string $number,
        public string $donorName,
        public CalendarDate $issuedOn,
        public CalendarDate $receivedOn,
        public int $amountMinor,
        public string $currency,
        public string $method,
        public ?string $reference,
        public bool $voided,
        public ?CalendarDate $reversedOn,
        public ?string $reversalReason,
        public array $lines,
    ) {
    }
}
