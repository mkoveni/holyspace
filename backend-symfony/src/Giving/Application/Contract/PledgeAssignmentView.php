<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use DateTimeImmutable;

/** A pledge assignment row (Java serialises the `PledgeAssignment` record itself). */
final readonly class PledgeAssignmentView
{
    public function __construct(
        public string $id,
        public string $pledgeId,
        public string $giftId,
        public string $fundId,
        public string $donorId,
        public string $currency,
        public int $amountMinor,
        public string $status,
        public int $version,
        public ?string $cancellationReason,
        public ?string $cancelledBy,
        public ?DateTimeImmutable $cancelledAt,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
