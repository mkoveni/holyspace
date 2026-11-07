<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Shared\Domain\CalendarDate;

/** Ledger, list and totals filter (Java GivingReader.Filter); validate with GivingFilters before reading. */
final readonly class GiftFilter
{
    public function __construct(
        public ?CalendarDate $from,
        public ?CalendarDate $to,
        public ?string $currency,
        public ?string $fundId,
        public ?string $donorId,
        public int $limit,
        public int $offset,
    ) {
    }
}
