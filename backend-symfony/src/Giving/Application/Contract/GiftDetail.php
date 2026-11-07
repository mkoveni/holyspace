<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Giving\Domain\Allocation;
use App\Giving\Domain\Money;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Gift evidence with its allocations (by fund id), fund names, full reversal and GIFT history (Java GivingReader.Detail). */
final readonly class GiftDetail
{
    /**
     * @param list<Allocation>       $allocations
     * @param array<string, string>  $fundNames
     * @param list<GiftHistoryEntry> $history
     */
    public function __construct(
        public string $id,
        public ?string $donorId,
        public Money $money,
        public CalendarDate $receivedOn,
        public string $method,
        public ?string $externalReference,
        public array $allocations,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        public string $donorName,
        public array $fundNames,
        public ?ReversalView $reversal,
        public array $history,
        public int $refundedMinor,
    ) {
    }
}
