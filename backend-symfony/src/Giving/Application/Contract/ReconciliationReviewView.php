<?php

declare(strict_types=1);

namespace App\Giving\Application\Contract;

use DateTimeImmutable;

/** One review of a batch (Java `Reconciliation.Review`). */
final readonly class ReconciliationReviewView
{
    public function __construct(
        public string $id,
        public string $batchId,
        public int $version,
        public string $outcome,
        public string $note,
        public int $reversedMinor,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
