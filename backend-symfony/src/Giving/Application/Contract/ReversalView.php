<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

final readonly class ReversalView
{
    public function __construct(
        public string $id,
        public string $giftId,
        public CalendarDate $reversedOn,
        public string $reason,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
