<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use App\Volunteering\Application\Contract\StaffingSummary;
use DateTimeImmutable;

final readonly class ReadinessView
{
    /** @param list<ReadinessCheck> $checks */
    public function __construct(
        public array $checks,
        public StaffingSummary $staffing,
        public ReadinessReview $review,
        public CloseoutView $closeout,
        public bool $changedSinceCloseout,
        public DateTimeImmutable $assessedAt,
    ) {
    }
}
