<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Giving\Domain\Money;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** A pledge row (Java serialises the `Pledge` record itself). */
final readonly class PledgeView
{
    public function __construct(
        public string $id,
        public string $donorId,
        public string $fundId,
        public Money $target,
        public CalendarDate $startsOn,
        public CalendarDate $endsOn,
        public string $status,
        public int $version,
        public ?string $cancellationReason,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
