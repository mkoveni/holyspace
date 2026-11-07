<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** The frozen batch as created (Java `Reconciliation`); gift ids in the order of its items. */
final readonly class ReconciliationBatchView
{
    /** @param list<string> $giftIds */
    public function __construct(
        public string $id,
        public string $reference,
        public int $expectedMinor,
        public int $actualMinor,
        public string $currency,
        public CalendarDate $reconciledOn,
        public string $reason,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        public array $giftIds,
    ) {
    }
}
