<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class ReadinessReview
{
    public function __construct(
        public OccurrenceView $occurrence,
        public AttendanceCounts $attendance,
        public NoticeTotals $notices,
        public string $hash,
        public bool $canClose,
    ) {
    }
}
